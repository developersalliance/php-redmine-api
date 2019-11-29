<?php

require_once 'vendor/autoload.php';
include_once 'connection.php';

$groupId = 74;
$group = $client->group->show($groupId, ['include' => 'users']);

$headerMessage = sprintf('%s (%s)', '## Daily Ticket Report', date('Y-m-d'));
echo $headerMessage;
$channel->postMessage($headerMessage);

$userIssues = $client->issue->all(
    [
        'status_id' => 'open',
        'limit' => '100'
    ]
);

$unassignedIssues = [];
foreach ($userIssues['issues'] as $issue) {
    if (!isset($issue['assigned_to'])) {
        $timeEntry = getTimeEntries($issue['id'], $client);
        $estimatedTime = isset($issue['estimated_hours']) ? $issue['estimated_hours'] : 0;

        $format = '[Ticket](%s/issues/%s) [%s][%s][%s] (%s) estimated time is %s and spent %s.';

        if ($timeEntry > $estimatedTime | 0 === $estimatedTime) {
            $format = sprintf('*%s*', $format);
        }

        $unassignedIssues[] = sprintf(
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
}

$message = "\n".'*Unassigned*'."\n\n";
foreach ($unassignedIssues as $item) {
    $message .= $item."\n";
}

$channel->postMessage($message);

foreach ($group['group']['users'] as $user) {
    $status = [];
    $userIssues = $client->issue->all(
        [
            'assigned_to_id' => $user['id'],
            'status_id' => 'open'
        ]
    );

    foreach ($userIssues['issues'] as $issue) {
        $timeEntry = getTimeEntries($issue['id'], $client);
        $estimatedTime = isset($issue['estimated_hours']) ? $issue['estimated_hours'] : 0;

        $format = '[Ticket](%s/issues/%s) [%s][%s][%s] (%s) estimated time is %s and spent %s.';

        if ($timeEntry > $estimatedTime | 0 === $estimatedTime) {
            $format = sprintf('*%s*', $format);
        }

        $key = $issue['assigned_to']['name'] ?? 'Unassigned';

        $status[$issue['status']['name']][] = sprintf(
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

    $message = '#### '.$user['name'];

    foreach ($status as $statusName => $items) {
        $message .= "\n".'*'.$statusName.'*'."\n\n";
        foreach ($items as $item) {
            $message .= $item."\n";
        }
    }

    echo $message;
    $channel->postMessage($message);
}

function getTimeEntries($issueId, $client)
{
    sleep(2);
    $timeEntries = $client->time_entry->all([
        'issue_id' => $issueId,
    ]);

    $total = 0;

    foreach ($timeEntries['time_entries'] as $timeEntry) {
        $total += $timeEntry['hours'];
    }

    return $total;
}
