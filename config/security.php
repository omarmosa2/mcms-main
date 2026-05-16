<?php

return [
    'public_registration_enabled' => (bool) env('SECURITY_PUBLIC_REGISTRATION_ENABLED', false),

    'invitation_expiration_days' => (int) env('SECURITY_INVITATION_EXPIRATION_DAYS', 7),

    'invitable_roles' => [
        'receptionist',
        'doctor',
        'accountant',
        'admin',
        'clinic_admin',
    ],

    'policy_defaults' => [
        'password_min_length' => 12,
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'session_lifetime_minutes' => 120,
        'idle_timeout_minutes' => 30,
        'force_two_factor' => false,
        'confirm_password_for_security_actions' => true,
        'audit_retention_days' => 365,
        'sensitive_access_retention_days' => 365,
    ],
];
