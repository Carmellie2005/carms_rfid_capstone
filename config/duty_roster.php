<?php

return [
    'timezone' => env('DUTY_ROSTER_TIMEZONE', 'Asia/Manila'),

    // The first rotation day starts with BCP-0002, then advances one guard per day.
    'rotation_start_date' => env('DUTY_ROSTER_START_DATE', '2026-10-18'),
    'rotation_start_guard' => env('DUTY_ROSTER_START_GUARD', 'BCP-0002'),
    'rotation_guards' => [
        'BCP-0001',
        'BCP-0002',
        'BCP-0003',
    ],
    'fixed_evening_guards' => [
        'BCP-0004',
    ],
];
