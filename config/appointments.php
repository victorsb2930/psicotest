<?php
return [
    // Defaults used when no global settings row exists.
    'presence_threshold_pct' => 97, // percent of scheduled duration required to auto-complete
    'early_access_minutes' => 5, // minutes before start time room becomes accessible
    'reschedule_deadline_hours' => 24, // hours before start after which reschedule not allowed
    'unanswered_reprogram_hours' => 5, // hours before start to auto mark skipped if reschedule unanswered
    'ping_interval_seconds' => 45, // heartbeat interval from clients
    'no_show_grace_minutes' => 10, // minutes after start before marking no_show/skipped if nobody/one joined
    // Quality metrics config (frontend & aggregation)
    'quality' => [
        'excellent' => [ 'bitrate_kbps_min' => 1500, 'loss_pct_max' => 2, 'rtt_ms_max' => 150 ],
        'acceptable' => [ 'bitrate_kbps_min' => 600, 'loss_pct_max' => 8, 'rtt_ms_max' => 300 ],
        // Degraded: anything below acceptable thresholds
        'max_samples' => 250, // cap samples stored per session to limit payload
    ],
    // Aggregation retention days (for pruning old daily rows)
    'aggregation_retention_days' => 180,
    // Alert thresholds: if previous day's aggregated metrics exceed limits, trigger notification
    'alerts' => [
        'loss_pct_warn' => 5.0, // average packet loss percent warning threshold
        'rtt_ms_warn' => 250.0, // average RTT ms warning threshold
        'retries_warn' => 1.5,  // average retries per session warning threshold
        'no_show_pct_warn' => 15.0, // percent no_show of total appointments warning threshold
    ],
    // Ratings
    'rating_window_days' => 7, // days after completion patient can rate
    'rating_edit_hours' => 2, // hours after initial rating allowed to edit
];
