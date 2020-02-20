<?php

require_once 'vendor/autoload.php';
include_once 'connection.php';

$groupId = 74;
$group = $client->group->show($groupId, ['include' => 'users']);

$channel = 'overspent';
$noEstimates = [];

if ($admin->login()) {
    $channel = new \RocketChat\Channel($channel, [$admin]);
}

foreach ($group['group']['users'] as $user) {
    $status = [];
    $userIssues = $client->issue->all(
        [
            'assigned_to_id' => $user['id'],
            'status_id' => 'open',
        ]
    );

    foreach ($userIssues['issues'] as $issue) {
        $estimatedTime = isset($issue['estimated_hours']) ? $issue['estimated_hours'] : 0;
        $timeEntry = getTimeEntries($issue['id'], $client);
        $format = '[Ticket](%s/issues/%s) [%s] "%s" (%s) estimated time is %s and spent %s.';

        if ($timeEntry > $estimatedTime) {
            $format = sprintf('*%s*', $format);

            $noEstimates[$issue['assigned_to']['name']][] = sprintf(
                $format,
                $url,
                $issue['id'],
                $issue['status']['name'],
                $issue['subject'],
                $issue['assigned_to']['name'],
                $estimatedTime,
                $timeEntry
            );
        }
    }
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

$message = '';
foreach ($noEstimates as $project => $tickets) {
    $message .= '#### '.$project."\n";

    foreach ($tickets as $ticket) {
        $message .= $ticket."\n";
    }

    $message .= "\n";
}

$channel->postMessage($message);
