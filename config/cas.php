<?php

declare(strict_types=1);

return [
    'octave_bridge_url' => env('OCTAVE_BRIDGE_URL', 'http://octave-bridge:8001'),
    'octave_bridge_timeout_seconds' => (int) env('OCTAVE_BRIDGE_TIMEOUT_SECONDS', 10),
    'octave_session_idle_hours' => (int) env('OCTAVE_SESSION_IDLE_HOURS', 24),
    'simulation_octave_timeout_seconds' => (int) env('SIMULATION_OCTAVE_TIMEOUT_SECONDS', 15),
    'cas_command_max_length' => (int) env('CAS_COMMAND_MAX_LENGTH', 4096),
    'cas_rate_limit_per_minute' => (int) env('CAS_RATE_LIMIT_PER_MINUTE', 30),
    'geolite_db_path' => env('GEOLITE_DB_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
    'geolite_license_key' => env('GEOLITE_LICENSE_KEY'),
    'stats_cooldown_minutes' => (int) env('STATS_COOLDOWN_MINUTES', 10),
    'stats_anon_token_salt' => env('STATS_ANON_TOKEN_SALT'),
    'request_log_retention_days' => (int) env('REQUEST_LOG_RETENTION_DAYS', 90),
    'csv_export_threshold' => (int) env('CSV_EXPORT_LARGE_THRESHOLD', 10000),
    'api_key_plaintext' => env('CAS_API_KEY_PLAINTEXT'),
    'cas_slowdown_ms' => (int) env('CAS_SLOWDOWN_MS', 500),
    'openapi_pdf_timeout_seconds' => (int) env('OPENAPI_PDF_TIMEOUT_SECONDS', 60),
    'browsershot_chrome_path' => env('BROWSERSHOT_CHROME_PATH', '/usr/bin/chromium'),
    'horizon_admin_token' => env('HORIZON_ADMIN_TOKEN', ''),
];
