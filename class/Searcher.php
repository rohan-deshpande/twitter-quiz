<?php

/**
 * Extension of the Quiz class
 * This class hits the search endpoint of the REST API and queries based on the app's @username
 * It checks to see if any tweets were missed by the streaming API and if so, processes them the same way Quiz would have done
 *
 * @author     Rohan Deshpande <rohan@creativelifeform.com>
 */

class Searcher extends Quiz
{
    protected $results;
    protected $statuses;

    public function __construct()
    {
        //override the Phirehose constructor;
        $this->results = false;
        $this->statuses = false;
    }

    public function search()
    {
        $this->results = $this->updater->querySearchAPI();

        if (empty($this->results['statuses']) || !$this->results) { 
            Logger::log($this->db , 'The query to the Search API found no mentions of '.USERNAME. ' using the parameters passed');
            return;
        }

        $this->statuses = $this->results['statuses'];
        $this->parseStatuses();
    }

    protected function parseStatuses()
    {
        if ($this->statuses === false) { 
            Logger::log($this->db , 'There have been no results since the last search');
            return;
        }
        foreach ($this->statuses as $data) {

            if (strpos(strtolower($data['text']) , FOCUS_HASHTAG) === false) continue;

            $queued = $this->queueTweet($data);

            if ($queued) {
                Logger::log($this->db , "A tweet with id " . $data['id_str'] . " was missed by the streaming API and captured by the REST API");
                $this->processStatus($data);
            } else {
                continue;
            }
        }
    }
}