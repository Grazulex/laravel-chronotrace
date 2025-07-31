<?php

return [
    'enabled' => true,
    'mode' => 'record_on_error',   // always | sample | record_on_error
    'sample_rate' => 0.001,        // si mode sample
    'storage' => 'local',          // local | s3
    'path' => storage_path('chronotrace'),
    'retention_days' => 15,
    'scrub' => ['password', 'token'],
];
