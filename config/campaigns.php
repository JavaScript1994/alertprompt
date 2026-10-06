<?php

declare(strict_types=1);

return [
    'batch_size' => (int) env('CAMPAIGN_BATCH_SIZE', 500),
    'failure_threshold' => (float) env('CAMPAIGN_FAILURE_THRESHOLD', 0.03),
];
