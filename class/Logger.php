<?php

/**
 * A simple logger to log data to the database
 *
 * @author Rohan Deshpande <rohan@creativelifeform.com>
 */
class Logger
{
    public static function log($db, $message)
    {
        $db->insert('log', [
            'datetime'  =>  date("Y-m-d H:i:s"),
            'message'   =>  $message
        ]);
    }

    public static function output($message)
    {
        if (DEBUG) return;
        $datetime = date("Y-m-d H:i:s");

        print '[' . (string)$datetime . ']: ' . $message . "\n";
    }
}
