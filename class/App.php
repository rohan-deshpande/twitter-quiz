<?php

/**
 * A controller to insantiate and run the quiz
 *
 * @author Rohan Deshpande <rohan@creativelifeform.com>
 */
class App
{
    private $quiz;

    /**
    * Sets the $this->quiz and runs its setup method.
    *
    * @return void
    */
    public function __construct()
    {
        $this->quiz = new Quiz(OAUTH_TOKEN, OAUTH_SECRET, Phirehose::METHOD_FILTER);
        $this->quiz->setup();
    }

    /**
    * If not in debug mode, runs the consume method of the quiz class.
    *
    * @return void
    */
    public function run()
    {
        if (!DEBUG) $this->quiz->consume();
    }
}
