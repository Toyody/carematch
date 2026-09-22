<?php

namespace App\Modules\Recruitment\Domain;

enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Applied => in_array($target, [self::Screening, self::Rejected], true),
            self::Screening => in_array($target, [self::Interview, self::Rejected], true),
            self::Interview => in_array($target, [self::Offer, self::Rejected], true),
            self::Offer => in_array($target, [self::Hired, self::Rejected], true),
            self::Hired, self::Rejected => false,
        };
    }
}
