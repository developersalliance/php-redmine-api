<?php

require_once 'vendor/autoload.php';
include_once 'connection.php';

$groupId = 81;
$group = $client->group->show($groupId, ['include' => 'users']);

$channel = 'estimates';

if ($admin->login()) {
    $channel = new \RocketChat\Channel($channel, [$admin]);
}

$monthIni = new DateTime('first day of last month');
$from = $monthIni->format('Y-m-d'); // 2012-02-01

foreach ($group['group']['users'] as $user) {
    $userIssues = $client->issue->all(
        [
            'assigned_to_id' => $user['id'],
            'updated_on' => '>='.$from,
            'limit' => '100',
        ]
    );

    foreach ($userIssues['issues'] as $issue) {
        $estimatedTime = isset($issue['estimated_hours']) ? $issue['estimated_hours'] : 0;
        $estimatedTime += $estimatedTime * 0.25;
        getTimeEntries($issue['id'], $estimatedTime, $issue['project']['id'], $user['id'], $user['name'], $issue, $client);
    }
}

function getTimeEntries($issueId, $estimatedTime, $projectId, $userId, $userName, $issue, $client)
{
    $monthIni = new DateTime('first day of last month'); // first day of this month
    $monthEnd = new DateTime('last day of last month'); // last day of this month

    $from = $monthIni->format('Y-m-d'); // 2012-02-01
    $to = $monthEnd->format('Y-m-d'); // 2012-02-29

    $timeEntries = $client->time_entry->all([
        'issue_id' => $issueId,
        'from' => $from,
        'to' => $to,
    ]);

    if (empty($timeEntries['time_entries'])) {
        return false;
    }

    $totalDevelopment = 0;
    $developmentTimeEntries = [];

    foreach ($timeEntries['time_entries'] as $timeEntry) {
        if (9 === $timeEntry['activity']['id']) { // Development
            $totalDevelopment += $timeEntry['hours'];
            $developmentTimeEntries[$timeEntry['id']] = $timeEntry['hours'];
        }
    }

    $overspentPercent = round($totalDevelopment / $estimatedTime, 2);

    // If time logged
    if ($totalDevelopment > 0) {
        // If issue estimate zero
        if ((float) 0 === $estimatedTime) {
            echo sprintf('[Estimate Needed] https://tickets.developers-alliance.com/issues/%s [%s] "%s" (%s).',
                    $issue['id'],
                    $issue['project']['name'],
                    $issue['subject'],
                    $issue['assigned_to']['name']
                )."\n";

            return false;
        }

        if ((int) $totalDevelopment <= (int) $estimatedTime) {
            return false;
        }

        // Set development 0
        $fixedDevelopment = 0;
        foreach ($developmentTimeEntries as $developmentTimeEntry => $hours) {
            $client->time_entry->update($developmentTimeEntry, [
                'issue_id' => $issueId,
                'hours' => $hours / $overspentPercent,
                'user_id' => $userId,
            ]);
            $fixedDevelopment += $hours / $overspentPercent;
        }

        // Create non billable
        if ($totalDevelopment > $estimatedTime) {
            $client->time_entry->create([
                'project_id' => $projectId,
                'hours' => $totalDevelopment - $fixedDevelopment,
                'activity_id' => 10, // Non billable
                'comments' => sprintf('[%s] Non Billable', $userName),
                'issue_id' => $issueId,
                'spent_on' => $to,
                'user_id' => $userId,
            ]);
        }

        echo sprintf('[Time Updated] https://tickets.developers-alliance.com/issues/%s [%s] "%s" (%s).',
                $issue['id'],
                $issue['project']['name'],
                $issue['subject'],
                $issue['assigned_to']['name']
            )."\n";
    }
}
