<?php

// Twitter account
const TWITTER_CONSUMER_KEY = '';
const TWITTER_CONSUMER_SECRET = '';
const TWITTER_ERROR_INTERVAL = 10;
const TWITTER_ERROR_ADDRESS = 'user@example.com';
const OAUTH_TOKEN = '';
const OAUTH_SECRET = '';
const USER_ID = 1;
const USERNAME = '@';

// Database
const DB_TYPE = 'mysql';
const DB_PORT = '3306';
const DB_NAME = 'testing';
const DB_SERVER = '127.0.0.1';
const DB_USER = 'testing';
const DB_PASS = 'testing';


// App
const TIME_ZONE = 'Australia/Sydney';

date_default_timezone_set(TIME_ZONE);

const INCORRECT_ANSWER = "";
const ANSWERED = "INCORRECT_ANSWER";
const REPLY_PREFIX_A = 'REPLY_PREFIX_A ';
const REPLY_PREFIX_B = 'REPLY_PREFIX_B ';
const REPLY_PREFIX_C = 'REPLY_PREFIX_C ';
const INCORRECT_ANSWER_A = " INCORRECT_ANSWER_A";
const INCORRECT_ANSWER_B = " INCORRECT_ANSWER_B";
const INCORRECT_ANSWER_C = " INCORRECT_ANSWER_C";
const INCORRECT_ANSWER_D = " INCORRECT_ANSWER_D";
const INCORRECT_ANSWER_E = " INCORRECT_ANSWER_E";
const INCORRECT_ANSWER_F = " INCORRECT_ANSWER_F";
const INCORRECT_ANSWER_G = " INCORRECT_ANSWER_G";
const INCORRECT_ANSWER_H = " INCORRECT_ANSWER_H";
const INCORRECT_ANSWER_I = " INCORRECT_ANSWER_I";
const INCORRECT_ANSWER_J = " INCORRECT_ANSWER_J";
const QUIZ_COMPLETE = "QUIZ_COMPLETE";
const QUIZ_FAILED = "QUIZ_FAILED";
const QUIZ_END = "QUIZ_END";
const LOG_QUIZ_START = 'The cron has hit Manager.php and started the quiz';
const LOG_QUIZ_END = 'The cron has hit Manager.php and ended the quiz';
const LOG_QUIZ_RUNNING = 'The cron has hit Manager.php and the quiz is running';
const MAX_POINTS = 100;
const DEBUFF = 5;
const BONUS_LIMIT = 10;
const RETWEET_LIMIT = 100;
const RETWEET_POINTS = 10;
const FOCUS_HASHTAG = 'FOCUS_HASHTAG';
const HASHTAG_POINTS = 10;
const FOLLOW_POINTS = 10;
const DEBUG = false;

/**
*   Libraries
*/

require_once('lib/Phirehose.php');
require_once('lib/OauthPhirehose.php');
require_once('lib/tmhOAuth.php');
require_once('lib/Medoo.php');

if (DEBUG) {
    require_once('class/ChromePhp.php');
}

/**
*   Classes;
*/

require_once('class/Quiz.php');
require_once('class/Updater.php');
require_once('class/Awarder.php');
require_once('class/Logger.php');
require_once('class/Searcher.php');
