<?php

require_once 'vendor/autoload.php';
include_once 'connection.php';

$statuses = [
    'Production' => 10,
    'Resolved' => 3,
    'Closed' => 5,
];

checkProductionStatus(10, $client);
checkResolvedStatus(3, $client);

function checkProductionStatus($statusId, $client)
{
    $issues = $client->issue->all([
        'status_id' => $statusId,
        'limit' => '100',
    ]);

    foreach ($issues['issues'] as $issue) {
        if (strtotime($issue['updated_on']) < strtotime('-7 day')) {
            $client->issue->setIssueStatus($issue['id'], 'Resolved');
        }
    }
}

function checkResolvedStatus($statusId, $client)
{
    $issues = $client->issue->all([
        'status_id' => $statusId,
        'limit' => '100',
    ]);

    foreach ($issues['issues'] as $issue) {
        if (strtotime($issue['updated_on']) < strtotime('-7 day')) {
            $client->issue->setIssueStatus($issue['id'], 'Closed');
        }
    }
}
