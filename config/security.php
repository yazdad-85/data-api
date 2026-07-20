<?php

return [
    'mfa' => [
        'super_admin_required' => (bool) env('MFA_SUPER_ADMIN_REQUIRED', true),
        'pending_ttl_minutes' => (int) env('MFA_PENDING_TTL_MINUTES', 10),
        'totp_window' => (int) env('MFA_TOTP_WINDOW', 1),
    ],
];
