<?php

/**
 * Extension of the tmhOAuth class
 * This class handles connections to the twitter REST API
 *
 * @author     Rohan Deshpande <rohan@creativelifeform.com>
 * @version    0.0.1
 */

class Updater extends tmhOAuth
{
    /**
    *   -- CONSTRUCTOR -- 
    *   Constructs the tmhOAuth object with custom config;
    *   @return void;
    */
    
    public function __construct($config = array())
    {
        $this->config = array_merge(array(
            'consumer_key'    => TWITTER_CONSUMER_KEY,
            'consumer_secret' => TWITTER_CONSUMER_SECRET,
            'token'           => OAUTH_TOKEN,
            'secret'          => OAUTH_SECRET,
            'bearer'          => '',
            'user_agent'      => 'tmhOAuth ' . parent::VERSION . ' Examples 0.1',
        ) , $config);

        parent::__construct($this->config);
    }

    /**
    *   Sends a status update to Twitter using the REST API;
    *   Determines which endpoint to hit based on whether the tweet has media associated with it or not;
    *   @param {$status['status']} a string to post as the status update;
    *   @param {$status['media']}[optional] an absolute URL to an image;
    *   @return {$response_code} the response code from Twitter;
    *   @return {false} if $status['status'] is not set or if the request fails;
    */

    public function Tweet($status)
    {
        $response_code = false;

        Logger::output('running Updater->Tweet()');

        if (!isset($status['status'])) return false;

        // Logger::output('Sleeping for 10 seconds... ');
        // sleep(10);

        if (isset($status['media']) && $status['media'] !== null && $status['media']) {
            $media = file_get_contents($status['media']);
            $response_code = $this->request(
                'POST',
                'https://api.twitter.com/1.1/statuses/update_with_media.json',
                [
                    'media[]' => $media,
                    'status' => $status['status'],
                    'in_reply_to_status_id' => $status['in_reply_to_status_id']
                ],
                true, // use auth
                true  // multipart
            );
        } else {
            $response_code = $this->user_request(array(
                'method'    =>  'POST',
                'url'       =>  $this->url("1.1/statuses/update"),
                'params'    =>  [
                                    'status' => $status['status'],
                                    'in_reply_to_status_id' => $status['in_reply_to_status_id']
                                ],
                'multipart' =>  false
            ));
        }

        Logger::output('completed Updater->Tweet()');

        return $response_code;
    }

    /**
    *   Returns an array of user objects who are the app account's followers;
    *   Queries the REST API twice at different end points to achieve this;
    *   @return {$followers} an array of stdClass objects containing user data;
    */

    public function getFollowers()
    {
        Logger::output('running Updater->getFollowers()');

        $followers = [];
        $response_code = $this->request(
            'GET',
            'https://api.twitter.com/1.1/followers/ids.json',
            ['user_id' => USER_ID , 'stringify_ids' => false],
            true
        );

        if ($response_code == 200) {
            $follower_ids = json_decode($this->response['response']);
            if (!empty($follower_ids->ids)) {
                foreach ($follower_ids->ids as $id) {
                    $code = $this->request(
                        'GET',
                        'https://api.twitter.com/1.1/users/lookup.json',
                        ['user_id' => $id],
                        true
                    );
                    if ($code == 200) {
                        $response_array = json_decode($this->response['response']);
                        $followers[] = array_shift($response_array);
                    }
                }
            }
        }

        Logger::output('completed Updater->getFollowers()');

        return $followers;
    }

    public function querySearchAPI()
    {
        Logger::output('running Updater->querySearchAPI()');

        $response_code = $this->request(
            'GET',
            'https://api.twitter.com/1.1/search/tweets.json',
            ['q' => USERNAME , 'result_type' => 'recent' ],
            true
        );

        if ($response_code == 200) {
            return json_decode($this->response['response'] , true);
        }
        else {
            return false;
        }
    }
}