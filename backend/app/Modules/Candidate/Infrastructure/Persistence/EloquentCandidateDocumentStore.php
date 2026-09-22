<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Data\CandidateDocumentRecord;
use App\Modules\Candidate\Application\Data\CandidateDocumentUpload;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentPersistenceFailure;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Throwable;

final class EloquentCandidateDocumentStore implements CandidateDocumentStore
{
    public function list(int $organisationId, int $candidateId): array
    {
        $documents = CandidateDocument::query()
            ->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (CandidateDocument $document): CandidateDocumentRecord => self::toRecord($document))
            ->all();

        return array_values($documents);
    }

    public function create(
        int $organisationId,
        int $candidateId,
        int $uploadedByUserId,
        string $storageKey,
        CandidateDocumentUpload $upload,
    ): CandidateDocumentRecord {
        $document = CandidateDocument::query()->create([
            'organisation_id' => $organisationId,
            'candidate_id' => $candidateId,
            'original_name' => $upload->originalName,
            'storage_key' => $storageKey,
            'mime_type' => $upload->mimeType,
            'size_bytes' => $upload->sizeBytes,
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);

        return self::toRecord($document);
    }

    public function find(
        int $organisationId,
        int $candidateId,
        int $documentId,
    ): ?CandidateDocumentRecord {
        $document = CandidateDocument::query()
            ->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)
            ->whereKey($documentId)
            ->first();

        return $document === null ? null : self::toRecord($document);
    }

    public function delete(
        int $organisationId,
        int $candidateId,
        int $documentId,
    ): bool {
        try {
            return CandidateDocument::query()
                ->where('organisation_id', $organisationId)
                ->where('candidate_id', $candidateId)
                ->whereKey($documentId)
                ->delete() === 1;
        } catch (Throwable) {
            throw new CandidateDocumentPersistenceFailure;
        }
    }

    private static function toRecord(CandidateDocument $document): CandidateDocumentRecord
    {
        return new CandidateDocumentRecord(
            id: (int) $document->getKey(),
            organisationId: (int) $document->getAttribute('organisation_id'),
            candidateId: (int) $document->getAttribute('candidate_id'),
            originalName: (string) $document->getAttribute('original_name'),
            storageKey: (string) $document->getAttribute('storage_key'),
            mimeType: (string) $document->getAttribute('mime_type'),
            sizeBytes: (int) $document->getAttribute('size_bytes'),
            uploadedByUserId: (int) $document->getAttribute('uploaded_by_user_id'),
            createdAt: self::date($document, 'created_at'),
            updatedAt: self::date($document, 'updated_at'),
        );
    }

    private static function date(CandidateDocument $document, string $attribute): DateTimeImmutable
    {
        $value = $document->getAttribute($attribute);

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException("Candidate document {$attribute} is not a date.");
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
