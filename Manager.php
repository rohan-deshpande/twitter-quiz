<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require_once('config.php');

class Manager
{
    public $running;
    private $db;
    protected $start_params;
    protected $end_params;
    protected $time;
    protected $start_time;
    protected $end_time;
    protected $updater;
    protected $awarder;
    protected $searcher;

    /**
    * -- CONSTRUCTOR --
    * Sets up various properties for this class and instantiates an Updater and Awarder;
    * @return void
    */
    public function __construct()
    {
        $this->db = new Medoo([
            'database_type' => DB_TYPE,
            'port'          => DB_PORT,
            'database_name' => DB_NAME,
            'server'        => DB_SERVER,
            'username'      => DB_USER,
            'password'      => DB_PASS
        ]);

        $this->start_params = ['status' => false, 'media' => false];
        $this->end_params = ['status' => false, 'media' => false];
        $this->time = time();
        $this->start_time = strtotime($this->db->get('startend', 'start_time', ['id' => 1]));
        $this->end_time = strtotime($this->db->get('startend', 'end_time', ['id' => 1]));
        $this->running = (boolean)$this->db->get('state', 'running', ['id' => 1]);
        $this->updater = new Updater();
        $this->awarder = new Awarder($this->db);
        $this->searcher = new Searcher();
    }

    /**
    * Sets the start end end parameters for the manager;
    * These parameters are passed to the Updater in order for status updates to be tweeted via the app;
    * @return {false} if no questions are found;
    * @return {true} if questions are found;
    */

    protected function setParameters()
    {
        $first = $this->db->select( 'questions', ['question', 'image'], ['AND' => ['active' => 1, 'question_order' => 0]] );

        if (!is_array($first)) return false;

        $first_question = array_shift($first);

        if (!is_array($first_question)) return false;

        $this->start_params = [
            'status'    =>  $first_question['question'],
            'media'     =>  $first_question['image']
        ];

        $this->end_params = [
            'status'    =>  QUIZ_END
        ];

        return true;
    }

    /**
    * Manages turning the quiz on and off.
    * Runs various methods during each condition.
    *
    * @return void
    */
    public function manage()
    {
        if (!$this->setParameters()) return;

        if (!$this->running)
        {
            if ($this->time >= $this->start_time && $this->time < $this->end_time) {
                Logger::log($this->db, LOG_QUIZ_START);
                Logger::output(LOG_QUIZ_START);
                $this->db->update( 'state', ['running' => 1], ['id' => 1] );
                $code = $this->updater->Tweet($this->start_params);
            }
        }
        else
        {
            if ($this->time >= $this->end_time) {
                Logger::log($this->db, LOG_QUIZ_END);
                Logger::output(LOG_QUIZ_END);
                $this->db->update( 'state', ['running' => 0], ['id' => 1] );
                $code = $this->updater->Tweet($this->end_params);
                $this->awarder->calculateAwards();
            } else {
                Logger::log($this->db, LOG_QUIZ_RUNNING);
                $this->searcher->setup();
                $this->searcher->search();
            }
        }

    }
}

$manager = new Manager();

$manager->manage();
