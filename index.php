<?php 
error_reporting(-1);
ini_set('display_errors', 'On');

require_once('config.php');
require_once('class/App.php');

$app = new App();
$app->run();

if (DEBUG) {
    require_once('tests.php');
}
