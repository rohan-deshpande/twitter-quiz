<?php

/**
 * Extension of the OauthPhirehose class
 * This class handles the logic of processing the tweet replies sent to the authorised account
 *
 * @author     Rohan Deshpande <rohan@creativelifeform.com>
 * @version    0.0.3
 */

class Quiz extends OauthPhirehose
{
    protected $db;
    protected $reply_prefixes;
    protected $incorrect_responses;
    protected $running;

    public $updater;

    /**
    *   Sets up the quiz;
    *   @return void;
    **/

    public function setup()
    {
        $this->setLang('en');
        $this->setFollow(array(USER_ID));
        $this->setTrack(array(USERNAME));
        $this->reply_prefixes = [
            REPLY_PREFIX_A,
            REPLY_PREFIX_B,
            REPLY_PREFIX_C
        ];
        $this->incorrect_responses = [
            INCORRECT_ANSWER_A,
            INCORRECT_ANSWER_B,
            INCORRECT_ANSWER_C,
            INCORRECT_ANSWER_D,
            INCORRECT_ANSWER_E,
            INCORRECT_ANSWER_F,
            INCORRECT_ANSWER_G,
            INCORRECT_ANSWER_H,
            INCORRECT_ANSWER_I,
            INCORRECT_ANSWER_J
        ];
        $this->updater = new Updater();
        $this->db = new Medoo([
            'database_type' => DB_TYPE,
            'port'          => DB_PORT,
            'database_name' => DB_NAME,
            'server'        => DB_SERVER,
            'username'      => DB_USER,
            'password'      => DB_PASS
        ]);
    }

    /**
    *   Checks to see if the quiz is running or not;
    *   @return {$running} a boolean value that determines if the quiz is running (true) or not (false);
    */

    protected function isRunning()
    {
        $running = $this->db->get('state' , 'running' , ['id' => 1]);
        return (boolean)$running;
    }

    /**
    *   Returns the current active quiz;
    *   @return {$quiz} an array containing the currently active rows from the questions table;
    */

    protected function getActiveQuiz()
    {
        $quiz = $this->db->select( 'questions' , ['question' , 'answer' , 'image'] , ['active' => 1 , "ORDER" => ['question_order']] );
        return $quiz;
    }

    /**
    *   Inserts a tweet into the queue table;
    *   @param {$data} the tweet data;
    *   @return {boolean} false if tweet already queued, true if it is not;
    */

    protected function queueTweet($data)
    {        
        $result = $this->db->select( 'queue' , 'tweet_id_str' , ['tweet_id_str' => $data['id_str']] );

        if (!empty($result)) {
            Logger::output('Tweet already exists');
            return false;
        }

        $last_insert_id = $this->db->insert('queue' , [
            'tweet_id_str'  =>  $data['id_str'],
            'tweet'         =>  serialize($data),
            'datetime'      =>  date("Y-m-d H:i:s")
        ]);

        if ($last_insert_id) {
            Logger::output('Tweet with id '.$data['id_str'].' queued');
            return true;
        }
    }

    /**
    *   Inserts a user into the players table;
    *   @param {$data} the tweet data;
    *   @return void;
    */

    protected function registerPlayer($data)
    {
        $registered = $this->db->select( 'players' , 'twitter_uid_str' , ['twitter_uid_str' => $data['user']['id_str']] );

        if (!empty($registered)) { 
            $this->db->update('players' , 
                ['twitter_username' =>  $data['user']['screen_name']], //data
                ['twitter_uid_str'  =>  $data['user']['id_str']] //condition
            );
            Logger::output('Player with username '.$data['user']['screen_name'].' already registered');
            return;
        }

        $last_insert_id = $this->db->insert('players' , [
            'twitter_username'  =>  $data['user']['screen_name'],
            'twitter_uid_str'   =>  $data['user']['id_str']
        ]);

        if ($last_insert_id)
        {
            Logger::output('Player with username '.$data['user']['screen_name'].' registered');
        }
    }

    /**
    *   Logs if a user has correctly answered to a question;
    *   @param {$data} the tweet data;
    *   @return void;
    */

    protected function recordAnswer($data , $answered , $result)
    {
        $last_insert_id = $this->db->insert('answers' , [
            'twitter_uid_str'   =>  $data['user']['id_str'],
            'status_id_str'     =>  $data['in_reply_to_status_id_str'],
            'answer_date'       =>  date('Y-m-d'),
            'answer_timestamp'  =>  strtotime($data['created_at']),
            'question_order'    =>  $answered,
            'question_id'       =>  $this->db->get('questions' , 'id' , ['AND' => ['active' => 1 , 'question_order' => $answered]]),
            'result'            =>  (boolean)$result
        ]);
    }

    /**
    *   Logs a retweet;
    *   @param {$data} the tweet data;
    *   @return void;
    */

    protected function recordRetweet($data)
    {
        $recorded = $this->db->select('retweets' , 'retweet_id_str' , ['retweet_id_str' => $data['id_str']]);
        if (!empty($recorded)) return;

        $last_insert_id = $this->db->insert('retweets' , [
            'twitter_uid_str'   =>  $data['user']['id_str'],
            'retweet_id_str'    =>  $data['id_str'],
            'retweeted_id_str'  =>  $data['retweeted_status']['id_str'],
            'answer_date'       =>  date('Y-m-d')
        ]);

        if ($last_insert_id) {
            Logger::output('Retweet with by user @'.$data['user']['screen_name'].' has been recorded');
        }
    }

    /**
    *   Checks to see if a user has correctly replied to a question;
    *   @param {$data} the tweet data;
    *   @param ($question_order) the order of the question to check against
    *   @return void;
    */

    protected function answered($data , $question_order)
    {
        $answered = $this->db->select( 'answers' , 'id' , [
            'AND' => [
                'twitter_uid_str'   =>  $data['user']['id_str'],
                'answer_date'       =>  date('Y-m-d'),
                'question_order'    =>  $question_order
            ]
        ]);

        if (!empty($answered)) {
            return true;
        }

        return false;
    }

    /**
    *   Gets the question_order of questions the user HAS answered;
    *   @param {$data} the tweet data;
    *   @return {$answered} an array of ints as strings;
    */

    protected function getUserAnswered($data)
    {
        $answered = $this->db->select( 'answers' , 'question_order' , [
            'AND' => [
                'twitter_uid_str'   =>  $data['user']['id_str'],
                'answer_date'       =>  date('Y-m-d')
            ]
        ]);

        return $answered;
    }

    /**
    *   Gets the question string and index of that question (its order) for questions the user has NOT answered;
    *   @param {$user_answered} an array of ints that may be strings;
    *   @param {$questions} an array of question strings;
    *   @return {$mapped} an assoc array containing an unanswered question and its index;
    */

    protected function getUnanswered(array $user_answered , array $questions)
    {
        $keys = array_flip(array_map('intval', $user_answered));
        $unanswered = array_diff_key($questions, $keys);
        $next_question = array_shift($unanswered);
        $index = array_search($next_question , $questions);
        $mapped = ['question' => $next_question , 'index' => $index];

        return $mapped;
    }

    /**
    *   Returns a $reply array based on the tweet $data passed;
    *   Records correct answers in the `answers` table;
    *   @param {$data} the tweet data;
    *   @return {$reply} an array containing status, media and username keys;
    */

    public function getReply($data)
    {
        $reply = [
            'status'                =>  false,
            'media'                 =>  false,
            'username'              =>  $data['user']['screen_name'], 
            'in_reply_to_status_id' =>  $data['in_reply_to_status_id']
        ];
        $quiz_active = $this->getActiveQuiz();
        $tweet_processed = preg_replace('/\s+/' , ' ' , urldecode(strtolower($data['text'])));
        $answers_exploded = [];
        $answers = [];
        $questions = [];
        $questions_order = [];
        $images = [];
        $unanswered = [];
        $correct = false;
        $i = 0;
        $answered;
        $user_answers = $this->getUserAnswered($data);

        foreach ($quiz_active as $active) {
            $answers[] = strtolower($active['answer']);
            $questions[] = $active['question'];
            $images[] = $active['image'];
        }

        foreach ($answers as $string) {
            $string = str_replace(', ', ',', $string);
            $answers_exploded[] = explode(',' , $string);
        }

        $user_answer_count = count($user_answers);
        $questions_count = count($questions);

        if ($user_answer_count == $questions_count)
        {
            //@user has answered all the questions in the quiz today;

            return $reply;
        }

        foreach ($answers_exploded as $answer) {

            $i++;
            $correct = false;

            foreach ($answer as $needle) {

                $correct = (strpos($tweet_processed , $needle) > -1) ? true : false;

                if ($correct) {

                    $answering = $i - 1;
                    $answered = $this->answered($data , $answering);

                    if ($answered) {

                        //@user has answered this question but hasn't answered all questions today;

                        $question = $this->getUnanswered($user_answers , $questions);
                        $reply['status'] = '@' . $reply['username'] . ' ' . ANSWERED.$question['question'];

                        if ($images[$question['index']] !== null) {
                            $reply['media'] = $images[$question['index']];
                        }

                    } else {

                        //@user hasn't answered this question;

                        if ($user_answer_count == $questions_count - 1) {

                            //@user has answered all other questions and this is their final answer;

                            $reply['status'] = '@' . $reply['username'] . ' ' . QUIZ_COMPLETE;

                        } else {

                            //@user has more questions to answer;

                            $prefix = $this->reply_prefixes[array_rand($this->reply_prefixes , 1)];     
                            $user_answers[] = $answering;
                            $question = $this->getUnanswered($user_answers , $questions);
                            $reply['status'] = '@' . $reply['username'] . ' ' . $prefix.$question['question'];

                            if ($images[$question['index']] !== null) {
                                $reply['media'] = $images[$question['index']];
                            }
                        }

                        $this->recordAnswer($data , (int)$answering , $correct);
                    }
                    break;
                } 
                else {
                    $reply['status'] = '@' . $reply['username'] . $this->incorrect_responses[array_rand($this->incorrect_responses , 1)];
                }
            }

            if ($correct) break;
        }

        return $reply;
    }

    /**
    *   Processes a tweet status object according to the app's conditions and methods;
    *   @param {$data} a json_decoded tweet status object;
    *   @return void;
    */

    protected function processStatus($data)
    {
        $this->registerPlayer($data);

        if (isset($data['retweeted_status'])) {
            $this->recordRetweet($data);
        } else {
            $reply = $this->getReply($data);

            if (!$reply['status']) return;

            Logger::output(json_encode($reply));

            $code = $this->updater->Tweet($reply);
            
            if ($code && $code == 200) {
                Logger::output("a response was sent to @".$data['user']['screen_name']);
            } else {
                Logger::output("something went wrong, here's the response code: $code");
            }
        }
    }

    /**
    *   Called by the parent::consume methdod, handles processing of tweets
    *   @param {$status} a json encoded tweet object;
    *   @return void;
    */

    public function enqueueStatus($status) 
    {
        $this->running = $this->isRunning();
        if (!$this->running) { 
            Logger::output('The quiz is currently sleeping');
            return;
        }

        $data = json_decode($status, true);
        
        if (is_array($data) && $data['user']['id'] !== USER_ID && isset($data['id_str'])) {

            Logger::output($data['user']['screen_name'] . ': ' . urldecode($data['text']) . ' tweet_id: ' . $data['id_str']);

            if (strpos(strtolower($data['text']) , FOCUS_HASHTAG) === false) return;

            if (!$this->queueTweet($data)) return;

            $this->processStatus($data);
        }
    }
}
