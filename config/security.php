<?php

return [
    'mfa' => [
        'super_admin_required' => (bool) env('MFA_SUPER_ADMIN_REQUIRED', true),
        'pending_ttl_minutes' => (int) env('MFA_PENDING_TTL_MINUTES', 10),
        'totp_window' => (int) env('MFA_TOTP_WINDOW', 1),
    ],

    'api_key_pepper' => (string) env('API_KEY_PEPPER', ''),

    'api_rate_per_minute' => (int) env('API_RATE_PER_MINUTE', 120),

    'api_ip_rate_per_minute' => (int) env('API_IP_RATE_PER_MINUTE', 240),
];
