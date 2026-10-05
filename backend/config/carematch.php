<?php

return [
    'ai' => [
        'enabled' => filter_var(env('CARE_MATCH_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
        'provider' => env('AI_PROVIDER', 'openai'),
        'model' => env('AI_MODEL', ''),
        'queue' => env('AI_QUEUE', 'ai'),
        'max_receive_count' => (int) env('AI_MAX_RECEIVE_COUNT', 3),
        'connect_timeout_seconds' => (int) env('AI_CONNECT_TIMEOUT_SECONDS', 10),
        'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 90),
    ],
    'compliance' => [
        'expiry_warning_days' => (int) env('COMPLIANCE_EXPIRY_WARNING_DAYS', 30),
        'expiry_digest' => [
            'queue' => env('COMPLIANCE_EXPIRY_DIGEST_QUEUE', 'compliance'),
            'max_receive_count' => (int) env('COMPLIANCE_EXPIRY_DIGEST_MAX_RECEIVE_COUNT', 3),
        ],
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
