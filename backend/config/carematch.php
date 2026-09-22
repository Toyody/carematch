<?php

return [
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
];
