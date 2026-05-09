<?php

declare(strict_types=1);

return [
    'octave_bridge_url' => env('OCTAVE_BRIDGE_URL', 'http://octave-bridge:8001'),
    'octave_bridge_timeout_seconds' => (int) env('OCTAVE_BRIDGE_TIMEOUT_SECONDS', 10),
    'octave_session_idle_hours' => (int) env('OCTAVE_SESSION_IDLE_HOURS', 24),
    'simulation_octave_timeout_seconds' => (int) env('SIMULATION_OCTAVE_TIMEOUT_SECONDS', 15),
    'cas_command_max_length' => (int) env('CAS_COMMAND_MAX_LENGTH', 4096),
    'cas_rate_limit_per_minute' => (int) env('CAS_RATE_LIMIT_PER_MINUTE', 30),
    'stats_anon_token_salt' => env('STATS_ANON_TOKEN_SALT'),
    'request_log_retention_days' => (int) env('REQUEST_LOG_RETENTION_DAYS', 90),
    'csv_export_threshold' => (int) env('CSV_EXPORT_LARGE_THRESHOLD', 10000),
];
