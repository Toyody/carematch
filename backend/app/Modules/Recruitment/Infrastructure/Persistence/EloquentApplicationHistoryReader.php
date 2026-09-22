<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Application\Contracts\ApplicationHistoryReader;
use App\Modules\Recruitment\Application\Data\ApplicationStatusHistoryRecord;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentApplicationHistoryReader implements ApplicationHistoryReader
{
    public function forApplication(int $organisationId, int $applicationId): ?array
    {
        $applicationExists = RecruitmentApplication::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($applicationId)
            ->exists();

        if (! $applicationExists) {
            return null;
        }

        $records = ApplicationStatusHistory::query()
            ->where('organisation_id', $organisationId)
            ->where('application_id', $applicationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(static function (ApplicationStatusHistory $history): ApplicationStatusHistoryRecord {
                $from = $history->getAttribute('from_status');
                $to = $history->getAttribute('to_status');
                $createdAt = $history->getAttribute('created_at');

                if (($from !== null && ! $from instanceof ApplicationStatus)
                    || ! $to instanceof ApplicationStatus
                    || ! $createdAt instanceof DateTimeInterface) {
                    throw new LogicException('The application status history has invalid persisted values.');
                }

                $note = $history->getAttribute('note');

                return new ApplicationStatusHistoryRecord(
                    id: (int) $history->getKey(),
                    fromStatus: $from,
                    toStatus: $to,
                    changedByUserId: (int) $history->getAttribute('changed_by_user_id'),
                    note: is_string($note) ? $note : null,
                    createdAt: DateTimeImmutable::createFromInterface($createdAt),
                );
            })
            ->all();

        return array_values($records);
    }
}
