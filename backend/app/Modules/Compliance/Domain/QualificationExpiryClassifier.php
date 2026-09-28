<?php

namespace App\Modules\Compliance\Domain;

use DateTimeImmutable;

final class QualificationExpiryClassifier
{
    public function classify(?DateTimeImmutable $expiresOn, DateTimeImmutable $today, int $warningDays): QualificationStatus
    {
        if ($expiresOn === null || $expiresOn > $today->modify(sprintf('+%d days', $warningDays))) {
            return QualificationStatus::Valid;
        }

        return $expiresOn < $today ? QualificationStatus::Expired : QualificationStatus::Expiring;
    }
}
