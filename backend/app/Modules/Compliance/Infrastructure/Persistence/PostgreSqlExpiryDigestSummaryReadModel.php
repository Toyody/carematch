<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestSummaryReadModel;
use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PostgreSqlExpiryDigestSummaryReadModel implements ExpiryDigestSummaryReadModel
{
    public function forOrganisation(int $organisationId, DateTimeImmutable $today, int $warningDays): ExpiryDigestSummary
    {
        $warningBoundary = $today->modify(sprintf('+%d days', $warningDays));
        $row = DB::table('candidate_qualifications')
            ->where('organisation_id', $organisationId)
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', $warningBoundary->format('Y-m-d'))
            ->selectRaw(
                'count(*) filter (where expires_on < ?)::int as expired_count, count(*) filter (where expires_on >= ?)::int as expiring_count',
                [$today->format('Y-m-d'), $today->format('Y-m-d')],
            )->first();

        if ($row === null) {
            throw new LogicException('The expiry digest summary query returned no result.');
        }

        return new ExpiryDigestSummary(
            (int) $row->expired_count,
            (int) $row->expiring_count,
            $warningDays,
        );
    }
}
