<?php

return [
    'compliance' => [
        'expiry_warning_days' => (int) env('COMPLIANCE_EXPIRY_WARNING_DAYS', 30),
    ],
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'invitation_expiry_days' => (int) env('ORGANISATION_INVITATION_EXPIRY_DAYS', 7),
    'candidate_documents' => [
        'disk' => env('CANDIDATE_DOCUMENTS_DISK', 'candidate_documents'),
        'max_bytes' => 10 * 1024 * 1024,
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],
    'portfolio_demo' => [
        'enabled' => env('CARE_MATCH_DEMO_DATA', false),
        'public_mode' => env('CARE_MATCH_PUBLIC_DEMO', false),
        'email' => env('CARE_MATCH_DEMO_EMAIL', 'demo.admin@example.test'),
        'password' => env('CARE_MATCH_DEMO_PASSWORD'),
    ],
];
