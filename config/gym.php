<?php

return [
    'multibranch_enabled' => (bool) env('GYM_MULTIBRANCH_ENABLED', false),
    'branch_name' => env('GYM_BRANCH_NAME', 'Main Branch'),
    'system_version' => env('GYM_SYSTEM_VERSION', 'v1.01'),

    'members' => [
        'photo_disk' => env('GYM_MEMBER_PHOTO_DISK', 'public'),
        'photo_path' => env('GYM_MEMBER_PHOTO_PATH', 'members/photos'),
        'expiring_soon_days' => (int) env('GYM_EXPIRING_SOON_DAYS', 7),
    ],

    'backup' => [
        'path' => env('GYM_BACKUP_PATH', storage_path('app/backups')),
        'retention_count' => (int) env('GYM_BACKUP_RETENTION_COUNT', 30),
        'pg_dump_binary' => env('GYM_PG_DUMP_BINARY', 'pg_dump'),
        'psql_binary' => env('GYM_PSQL_BINARY', 'psql'),
    ],

    'access' => [
        'timeout' => (int) env('GYM_ACCESS_TIMEOUT', 8),
        'card_list_timeout' => (int) env('GYM_ACCESS_CARD_LIST_TIMEOUT', 30),
        'dahua_bridge_url' => env('GYM_DAHUA_BRIDGE_URL'),
        'dahua_bridge_health_url' => env('GYM_DAHUA_BRIDGE_HEALTH_URL', 'http://127.0.0.1:8787/health'),
        'dahua_bridge_host' => env('GYM_DAHUA_BRIDGE_HOST', '127.0.0.1'),
        'dahua_bridge_port' => (int) env('GYM_DAHUA_BRIDGE_PORT', 8787),
        'dahua_bridge_python' => env(
            'GYM_DAHUA_BRIDGE_PYTHON',
            PHP_OS_FAMILY === 'Windows'
                ? base_path('runtime/python-3.13.15/python.exe')
                : 'python3'
        ),
        'dahua_bridge_netsdk' => env('GYM_DAHUA_NETSDK_PATH'),
        'dahua_bridge_script' => env('GYM_DAHUA_BRIDGE_SCRIPT', base_path('scripts/dahua_sdk_bridge.py')),
        'dahua_bridge_pid_file' => env('GYM_DAHUA_BRIDGE_PID_FILE', storage_path('app/dahua-bridge.pid')),
        'dahua_bridge_log_file' => env('GYM_DAHUA_BRIDGE_LOG_FILE', storage_path('logs/dahua-bridge.log')),
        'dahua_bridge_restart_wait_ms' => (int) env('GYM_DAHUA_BRIDGE_RESTART_WAIT_MS', 700),
    ],

    'deployment' => [
        'enabled' => (bool) env('DEPLOYMENT_UPDATES_ENABLED', false),
        'secret' => env('DEPLOYMENT_SECRET'),
        'script_path' => env('DEPLOYMENT_SCRIPT_PATH', base_path('scripts/deploy.sh')),
        'timeout' => (int) env('DEPLOYMENT_TIMEOUT', 300),
    ],
];
