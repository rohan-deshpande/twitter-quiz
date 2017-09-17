<?php

/**
 * A class to process the points for each player
 *
 * @author     Rohan Deshpande <rohan@creativelifeform.com>
 * @version    0.0.2
 */
class Awarder
{
    private $db;
    public $todays_answers;
    protected $todays_retweets;
    protected $restAPI;

    /**
    *   Sets up a db object if one isn't passed, calls methods to set today's answers and retweets;
    *   Instantiates an instance of the Updater class.
    *   @param object|bool [$db] - a database connection object
    *   @return void
    */
    public function __construct($db = false)
    {
        Logger::output('running Awarder __construct()');

        if (!$db) {
            $this->db = new Medoo([
                'database_type' => DB_TYPE,
                'port'          => DB_PORT,
                'database_name' => DB_NAME,
                'server'        => DB_SERVER,
                'username'      => DB_USER,
                'password'      => DB_PASS
            ]);
        } else {
            $this->db = $db;
        }
        $this->setTodaysAnswers();
        $this->setTodaysRetweets();
        $this->restAPI = new Updater();

        Logger::output('completed Awarder __construct()');
    }

    /**
    *   Gets and sets the answers of the day;
    *   @return void;
    */
    protected function setTodaysAnswers()
    {
        Logger::output('running Awarder->setTodaysAnswers()');

        $date = date('Y-m-d');
        $query =    "SELECT a.* , p.twitter_username FROM `answers` as a
                    LEFT JOIN `players` as p
                    ON a.twitter_uid_str = p.twitter_uid_str
                    WHERE DATE(a.answer_date) = '$date'
                    AND (a.result) = '1'
                    ORDER BY a.question_order , a.answer_timestamp;";

        $this->todays_answers = $this->db->query($query)->fetchAll();

        Logger::output('completed Awarder->setTodaysAnswers()');
    }

    /**
    *   Gets and sets the retweets of the day;
    *   @return void;
    */
    protected function setTodaysRetweets()
    {
        Logger::output('running Awarder->setTodaysRetweets()');

        $date = date('Y-m-d');
        $query =    "SELECT r.* , p.twitter_username FROM `retweets` as r
                    LEFT JOIN `players` as p
                    ON r.twitter_uid_str = p.twitter_uid_str
                    WHERE DATE(r.answer_date) = '$date'
                    ORDER BY r.answer_date
                    LIMIT ".RETWEET_LIMIT.";";

        $this->todays_retweets = $this->db->query($query)->fetchAll();

        Logger::output('completed Awarder->setTodaysRetweets()');
    }

    /**
    *   Gives points using passed data which is retrieved from the answers table;
    *   @param {$table_data} the data retrieved;
    *   @return void;
    */
    protected function givePoints($table_data)
    {
        $id = $this->db->insert('points' , [
            'twitter_username'  =>  $table_data['twitter_username'],
            'twitter_uid_str'   =>  $table_data['twitter_uid_str'],
            'points_date'       =>  $table_data['answer_date'],
            'points'            =>  $table_data['points'],
            'type'              =>  $table_data['type']
        ]);
    }

    /**
    *   Looks up follower ids, then retrieves user objects using the restAPI;
    *   Gives 10 points to each new follower (not already registered as a player);
    *   @return void;
    */
    protected function rewardFollowers()
    {
        $followers = $this->restAPI->getFollowers();

        if (empty($followers)) return;

        Logger::output('running Awarder->rewardFollowers()');

        foreach ($followers as $f) {
            /**
            *   @var $f is an stdClass object;
            */

            $registered = $this->db->select('players' , 'twitter_uid_str' , ['twitter_uid_str' => $f->id_str]);
            if (empty($registered)) {
                $last_insert_id = $this->db->insert('players' , [
                    'twitter_uid_str'   =>  $f->id_str,
                    'twitter_username'  =>  $f->screen_name
                ]);
                $this->db->insert('points' , [
                    'twitter_username'  =>  $f->screen_name,
                    'twitter_uid_str'   =>  $f->id_str,
                    'points_date'       =>  date('Y-m-d H:i:s'),
                    'points'            =>  FOLLOW_POINTS,
                    'type'              =>  'follow'
                ]);
            }
        }

        Logger::output('completed Awarder->rewardFollowers()');
    }

    /**
    *   Calculates the awards for a player based on their activity on the day;
    *   Is called via the Manager if the quiz END_TIME has passed;
    *   @return void;
    */
    public function calculateAwards()
    {
        Logger::output('running Awarder->calculateAwards()');

        $i = -1;

        if (is_array($this->todays_answers) && !empty($this->todays_answers)) {
            foreach ($this->todays_answers as $a) {
                $i++;
                if ($i <= BONUS_LIMIT && $a['question_order'] == 0) {
                    $points = MAX_POINTS - $i * DEBUFF;
                    $a['points'] = $points;
                } else {
                    $a['points'] = MAX_POINTS - DEBUFF * BONUS_LIMIT;
                }

                $a['type'] = 'answer';
                $this->givePoints($a);
            }
        }

        if (is_array($this->todays_retweets) && !empty($this->todays_retweets)) {
            foreach ($this->todays_retweets as $r) {
                $r['points'] = RETWEET_POINTS;
                $r['type'] = 'retweet';
                $this->givePoints($r);
            }
        }

        $this->rewardFollowers();

        Logger::output('completed Awarder->calculateAwards()');
    }

    /**
    *   Loops through the hashtags contained in $tweet;
    *   If the focus hashtag is found, awards points;
    *   @param {$db} a database connection object;
    *   @param {$tweet} a tweet object;
    *   @return if no $index of the hashtag is found in the $tweet;
    */
    public static function checkHashtags($db , $tweet)
    {
        if (empty($tweet['entities']['hashtags'])) return;

        Logger::output('running Awarder->checkHashtags()');

        $index = false;

        foreach ($tweet['entities']['hashtags'] as $hashtag) {
            if (strtolower($hashtag['text']) == strtolower(FOCUS_HASHTAG)) {
                $index = true;
                break;
            }
        }

        if (!$index) return;

        $id = $db->insert('points' , [
            'twitter_username'  =>  $tweet['user']['screen_name'],
            'twitter_uid_str'   =>  $tweet['user']['id_str'],
            'points_date'       =>  date( 'Y-m-d H:i:s', strtotime($tweet['created_at']) ),
            'points'            =>  HASHTAG_POINTS,
            'type'              =>  'hashtag'
        ]);

        if ($id) {
            print '@ ' . $tweet['user']['screen_name'] . ' has been awared points for using the focus hashtag';
        }

        Logger::output('completed Awarder->checkHashtags()');
    }
}
