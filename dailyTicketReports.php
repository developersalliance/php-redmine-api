<?php

require_once 'vendor/autoload.php';
include_once 'connection.php';

$channel = 'vere-loft';

$statuses = [
    'Estimate' => 1,
    'In Progress' => 2,
    'Client Action' => 4,
    'For Development' => 7,
    'Code Review' => 9,
    'Staging' => 8,
    'For Production' => 11,
    'Production' => 10,
];

if ($admin->login()) {
    $channel = new \RocketChat\Channel($channel, [$admin]);
    $headerMessage = sprintf('%s (%s)', '## Daily Ticket Report', date('Y-m-d'));
    echo $headerMessage;
    $channel->postMessage($headerMessage);
}

$users = [];
foreach ($statuses as $status => $statusId) {
    $users = checkStatus($statusId, $status, $users, $client, $url);
}

$userName = '';

foreach ($users as $name => $user) {
    if ('' !== $userName && $userName !== $name) {
        continue;
    }

    $message = "\n".'### '.$name."\n";

    foreach ($user as $status => $items) {
        $message .= "\n".'#### '.$status."\n";
        foreach ($items as $item) {
            $message .= $item."\n";
        }
    }

    echo $message;
    $channel->postMessage($message);
}

/**
 * @param $statusId
 * @param $status
 * @param $users
 * @param $client
 * @param $url
 *
 * @return mixed
 */
function checkStatus($statusId, $status, $users, $client, $url)
{
    $issues = $client->issue->all([
        'status_id' => $statusId,
        'limit' => '100'
    ]);

    foreach ($issues['issues'] as $issue) {
        $timeEntry = getTimeEntries($issue['id'], $client);
        $estimatedTime = isset($issue['estimated_hours']) ? $issue['estimated_hours'] : 0;

        $format = '[Ticket](%s/issues/%s) [%s][%s][%s] (%s) estimated time is %s and spent %s.';

        if ($timeEntry > $estimatedTime | 0 === $estimatedTime) {
            $format = sprintf('*%s*', $format);
        }

        $key = $issue['assigned_to']['name'] ?? 'Unassigned';

        $users[$key][$status][] = sprintf(
            $format,
            $url,
            $issue['id'],
            $issue['id'],
            $issue['project']['name'],
            $issue['subject'],
            $issue['status']['name'],
            $estimatedTime,
            $timeEntry
        );
    }

    return $users;
}

function getTimeEntries($issueId, $client)
{
    $timeEntries = $client->time_entry->all([
        'issue_id' => $issueId,
    ]);

    $total = 0;

    foreach ($timeEntries['time_entries'] as $timeEntry) {
        $total += $timeEntry['hours'];
    }

    return $total;
}
