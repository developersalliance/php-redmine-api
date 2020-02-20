<?php

require_once 'vendor/autoload.php';
include_once 'connection.php';

$groupId = 74;
$group = $client->group->show($groupId, ['include' => 'users']);

$channel = 'time-entries';
$userEntries = [];

$spentOnFrom = date('Y-m-d', strtotime('-3 day'));
$spentOnTo = date('Y-m-d', strtotime('-1 day'));

if (date('w') === 1) {
    $spentOnFrom = date('Y-m-d', strtotime('-6 day'));
    $spentOnTo = date('Y-m-d', strtotime('-3 day'));
}

if ($admin->login()) {
    $channel = new \RocketChat\Channel($channel, [$admin]);
}

foreach ($group['group']['users'] as $user) {
    $status = [];
    $timeEntries = $client->time_entry->all([
        'from' => $spentOnFrom,
        'to' => $spentOnTo,
        'user_id' => $user['id'],
    ]);

    foreach ($timeEntries['time_entries'] as $timeEntry) {
        if (!isset($userEntries[$user['name']][$timeEntry['spent_on']])) {
            $userEntries[$user['name']][$timeEntry['spent_on']] = 0;
        }

        $userEntries[$user['name']][$timeEntry['spent_on']] += $timeEntry['hours'];
    }
}

$message = '';
foreach ($userEntries as $user => $timeentries) {
    $message .= '#### '.$user."\n";

    ksort($timeentries);

    foreach ($timeentries as $date => $hours) {
        $message .= date( "l", strtotime($date)) . ' (' . $date . ')' . ': ' . $hours . 'h' ."\n";
    }

    $message .= "\n";
}

$channel->postMessage($message);
