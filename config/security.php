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

    'api_sync_max_since_days' => (int) env('API_SYNC_MAX_SINCE_DAYS', 90),

    'headers' => [
        'csp' => env(
            'SECURITY_CSP',
            "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
        ),
        'hsts' => 'max-age=31536000; includeSubDomains',
    ],
];
