<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'invitation_expiry_days' => (int) env('ORGANISATION_INVITATION_EXPIRY_DAYS', 7),
];
