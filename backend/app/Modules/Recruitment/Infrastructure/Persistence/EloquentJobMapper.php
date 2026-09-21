<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Data\JobRecord;
use App\Modules\Recruitment\Domain\JobStatus;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentJobMapper
{
    public static function toRecord(Job $job): JobRecord
    {
        $status = $job->getAttribute('status');
        if (! $status instanceof JobStatus) {
            throw new LogicException('The job has an invalid status.');
        }

        return new JobRecord(
            id: (int) $job->getKey(),
            organisationId: (int) $job->getAttribute('organisation_id'),
            title: (string) $job->getAttribute('title'),
            occupation: self::nullableString($job, 'occupation'),
            location: self::nullableString($job, 'location'),
            employmentType: self::nullableString($job, 'employment_type'),
            description: self::nullableString($job, 'description'),
            status: $status,
            openedAt: self::nullableDate($job, 'opened_at'),
            closesAt: self::nullableDate($job, 'closes_at'),
            createdAt: self::date($job, 'created_at'),
            updatedAt: self::date($job, 'updated_at'),
        );
    }

    private static function nullableString(Job $job, string $attribute): ?string
    {
        $value = $job->getAttribute($attribute);

        return $value === null ? null : (string) $value;
    }

    private static function nullableDate(Job $job, string $attribute): ?DateTimeImmutable
    {
        $value = $job->getAttribute($attribute);

        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
    }

    private static function date(Job $job, string $attribute): DateTimeImmutable
    {
        return self::nullableDate($job, $attribute)
            ?? throw new LogicException("The job has no {$attribute} timestamp.");
    }
}
