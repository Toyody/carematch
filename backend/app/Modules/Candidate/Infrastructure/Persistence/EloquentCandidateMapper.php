<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use App\Modules\Candidate\Application\Data\CandidateRecord;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentCandidateMapper
{
    public static function toRecord(Candidate $candidate): CandidateRecord
    {
        return new CandidateRecord(
            id: (int) $candidate->getKey(),
            organisationId: (int) $candidate->getAttribute('organisation_id'),
            firstName: (string) $candidate->getAttribute('first_name'),
            lastName: (string) $candidate->getAttribute('last_name'),
            email: self::nullableString($candidate, 'email'),
            phone: self::nullableString($candidate, 'phone'),
            occupation: self::nullableString($candidate, 'occupation'),
            location: self::nullableString($candidate, 'location'),
            availability: self::nullableString($candidate, 'availability'),
            notes: self::nullableString($candidate, 'notes'),
            createdAt: self::date($candidate, 'created_at'),
            updatedAt: self::date($candidate, 'updated_at'),
        );
    }

    private static function nullableString(Candidate $candidate, string $attribute): ?string
    {
        $value = $candidate->getAttribute($attribute);

        return $value === null ? null : (string) $value;
    }

    private static function date(Candidate $candidate, string $attribute): DateTimeImmutable
    {
        $value = $candidate->getAttribute($attribute);

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException("The candidate has no {$attribute} timestamp.");
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
