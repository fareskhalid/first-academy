<?php

return [
    'timezone' => 'Africa/Cairo',
    'week_starts_on' => 6, // Carbon::SATURDAY; weekly scheduling arrives in Sprint 2.
    'free_attended_lessons' => 2, // Per student/offering, not delivered-class number.
    'attendance_opens_minutes_before' => 15,
    'attendance_closes_minutes_after_start' => 30,
    'late_minutes_after_start' => 10,
    'qr_rotate_seconds' => 30,
    'qr_token_seconds' => 60,
    'attendance_intent_minutes' => 2,
    'locales' => ['en', 'ar'],
];
