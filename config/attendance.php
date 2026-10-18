<?php

return [
    'start_time' => env('ATTENDANCE_START_TIME', '08:30'),
    'grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 15),
];
