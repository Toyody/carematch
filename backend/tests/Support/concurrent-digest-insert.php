<?php

declare(strict_types=1);

$values = json_decode((string) getenv('TEST_DIGEST_VALUES'), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($values) || count($values) !== 4) {
    throw new RuntimeException('Invalid concurrent digest test values.');
}

$startAt = (float) getenv('TEST_START_AT');
while (microtime(true) < $startAt) {
    usleep(1_000);
}

$pdo = new PDO(
    sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        getenv('TEST_DB_HOST'),
        getenv('TEST_DB_PORT'),
        getenv('TEST_DB_DATABASE'),
    ),
    (string) getenv('TEST_DB_USERNAME'),
    (string) getenv('TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$statement = $pdo->prepare(<<<'SQL'
    INSERT INTO compliance_expiry_digest_requests
        (organisation_id, requested_by_user_id, idempotency_key_hash, request_fingerprint,
         status, queued_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'queued', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ON CONFLICT (organisation_id, requested_by_user_id, idempotency_key_hash) DO NOTHING
    SQL);
$statement->execute(array_values($values));
