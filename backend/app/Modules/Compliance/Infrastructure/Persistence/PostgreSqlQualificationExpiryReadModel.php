<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Compliance\Application\Contracts\QualificationExpiryReadModel;
use App\Modules\Compliance\Application\Data\QualificationExpiryRecord;
use App\Modules\Compliance\Domain\QualificationStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class PostgreSqlQualificationExpiryReadModel implements QualificationExpiryReadModel
{
    public function expiringBy(int $organisationId, DateTimeImmutable $today, DateTimeImmutable $warningBoundary): array
    {
        return array_values(DB::table('candidate_qualifications as credentials')
            ->join('candidates', function ($join): void {
                $join->on('candidates.organisation_id', '=', 'credentials.organisation_id')
                    ->on('candidates.id', '=', 'credentials.candidate_id');
            })
            ->join('qualification_definitions as definitions', function ($join): void {
                $join->on('definitions.organisation_id', '=', 'credentials.organisation_id')
                    ->on('definitions.id', '=', 'credentials.qualification_definition_id');
            })
            ->where('credentials.organisation_id', $organisationId)
            ->whereNotNull('credentials.expires_on')
            ->whereDate('credentials.expires_on', '<=', $warningBoundary->format('Y-m-d'))
            ->orderBy('credentials.expires_on')->orderBy('credentials.id')
            ->get([
                'credentials.id as credential_id', 'credentials.candidate_id', 'credentials.qualification_definition_id',
                'credentials.expires_on', 'candidates.first_name', 'candidates.last_name', 'definitions.name as qualification_name',
            ])
            ->map(static function (object $row) use ($today): QualificationExpiryRecord {
                $expiresOn = new DateTimeImmutable((string) $row->expires_on);

                return new QualificationExpiryRecord(
                    (int) $row->credential_id,
                    (int) $row->candidate_id,
                    trim(sprintf('%s %s', $row->first_name, $row->last_name)),
                    (int) $row->qualification_definition_id,
                    (string) $row->qualification_name,
                    $expiresOn,
                    $expiresOn < $today ? QualificationStatus::Expired : QualificationStatus::Expiring,
                );
            })->all());
    }
}
