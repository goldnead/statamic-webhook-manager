<?php

/*
 * The words for the figures this addon contributes to statamic-insights.
 *
 * Their own file rather than a section of messages.php: the analytics addon is
 * optional, and a reader of that file should not have to work out which half of
 * it only applies when a sibling is installed.
 */

return [
    'group' => 'Webhooks',

    'deliveries' => 'Deliveries',
    'deliveries_description' => 'Events that had to go out in this period, dated by when the event happened rather than by when it was last retried.',

    'failures' => 'Failed deliveries',
    'failures_description' => 'Deliveries that gave up for good. One still waiting for a retry is not counted, and neither is one somebody cancelled.',

    'success_rate' => 'Success rate',
    'success_rate_description' => 'Of the deliveries that reached a verdict, the share that arrived. A delivery still queued for a retry has no verdict yet and is left out.',

    'latency_avg' => 'Response time (average)',
    'latency_avg_description' => 'How long a delivery took on average. Sub-second averages are shown as 0 s here; the exact milliseconds and the p50/p95/p99 percentiles are on this addon\'s own Insights screen.',

    'breakdown_status' => 'Status',

    'no_status' => 'Without a status',

    'status' => [
        'pending' => 'Waiting',
        'processing' => 'Going out',
        'success' => 'Arrived',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],
];
