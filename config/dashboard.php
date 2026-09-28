<?php

declare(strict_types=1);

return [
    'admin_stats_cache_ttl' => (int) env('DASHBOARD_ADMIN_STATS_CACHE_TTL', 300),
    'admin_kpi_cache_key' => 'dashboard:admin:kpi',
    'admin_completion_rate_cache_key' => 'dashboard:admin:completion-rate',
];
