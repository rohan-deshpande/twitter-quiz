<?php

$reply_test =
[
    'id_str' => '',
    'in_reply_to_status_id_str' => '1',
    'in_reply_to_status_id' => 1,
    'created_at' => 'Tue Feb 3 03:05:50 +0000 2015',
    'user'  =>  ['screen_name' => 'twitter-quiz' , 'id_str' => '1'],
    'text'  =>  'twitter-quiz'
];

$reply = $app->quiz->getReply($reply_test);

echo "<pre>";
print_r($reply);
echo "</pre>";

$awarder = new Awarder();

$awarder->calculateAwards();

echo "<pre>";
print_r($awarder->todays_answers);
echo "</pre>";
