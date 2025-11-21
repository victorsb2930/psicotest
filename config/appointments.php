<?php
return [
    // Defaults used when no global settings row exists.
    'presence_threshold_pct' => 97, // percent of scheduled duration required to auto-complete
    'early_access_minutes' => 5, // minutes before start time room becomes accessible
    'reschedule_deadline_hours' => 24, // hours before start after which reschedule not allowed
    'unanswered_reprogram_hours' => 5, // hours before start to auto mark skipped if reschedule unanswered
    'ping_interval_seconds' => 45, // heartbeat interval from clients
    // Ratings
    'rating_window_days' => 7, // days after completion patient can rate
    'rating_edit_hours' => 2, // hours after initial rating allowed to edit
];
