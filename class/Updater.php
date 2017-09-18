<?php

/**
 * This class handles connections to the twitter REST API
 *
 * @author Rohan Deshpande <rohan@creativelifeform.com>
 */
class Updater extends tmhOAuth
{
    /**
    * Constructs the tmhOAuth object with custom config.
    *
    * @return void
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
        ), $config);

        parent::__construct($this->config);
    }

    /**
    * Sends a status update to Twitter using the REST API.
    * Determines which endpoint to hit based on whether the tweet has media associated with it or not.
    *
    * @param array $status - an array containing a status string and optional image URL
    * @return int|bool $responseCode - the response code from the request or false if no status
    */
    public function Tweet($status)
    {
        $responseCode = false;

        Logger::output('running Updater->Tweet()');

        if (!isset($status['status'])) {
            return false;
        }

        if (isset($status['media']) && $status['media'] !== null && $status['media']) {
            $media = file_get_contents($status['media']);
            $responseCode = $this->request(
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
            $responseCode = $this->user_request(array(
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

        return $responseCode;
    }

    /**
    * Returns an array of user objects who are the app account's followers.
    * Queries the REST API twice at different end points to achieve this.
    *
    * @return array $followers - an array of stdClass objects containing user data
    */
    public function getFollowers()
    {
        Logger::output('running Updater->getFollowers()');

        $followers = [];
        $responseCode = $this->request(
            'GET',
            'https://api.twitter.com/1.1/followers/ids.json',
            ['user_id' => USER_ID, 'stringify_ids' => false],
            true
        );

        if ($responseCode == 200) {
            $followerIds = json_decode($this->response['response']);
            if (!empty($followerIds->ids)) {
                foreach ($followerIds->ids as $id) {
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

    /**
     * Queries the search API for USERNAME's recent tweets.
     *
     * @return array|bool
     */
    public function querySearchAPI()
    {
        Logger::output('running Updater->querySearchAPI()');

        $responseCode = $this->request(
            'GET',
            'https://api.twitter.com/1.1/search/tweets.json',
            ['q' => USERNAME, 'result_type' => 'recent' ],
            true
        );

        if ($responseCode == 200) {
            return json_decode($this->response['response'], true);
        }
        else {
            return false;
        }
    }
}
