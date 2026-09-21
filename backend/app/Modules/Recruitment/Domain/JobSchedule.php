<?php

namespace App\Modules\Recruitment\Domain;

use DateTimeInterface;

final class JobSchedule
{
    public static function isValid(?DateTimeInterface $openedAt, ?DateTimeInterface $closesAt): bool
    {
        return $openedAt === null || $closesAt === null || $closesAt >= $openedAt;
    }
}
