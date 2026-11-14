<?php

return [
    'cookie' => env('DEVICE_SESSION_COOKIE', 'pawhaven-device'),
    'idle_days' => (int) env('DEVICE_SESSION_IDLE_DAYS', 30),
    'rotation_grace_seconds' => (int) env('DEVICE_SESSION_ROTATION_GRACE_SECONDS', 30),
    'retention_days' => (int) env('DEVICE_SESSION_RETENTION_DAYS', 30),
];
