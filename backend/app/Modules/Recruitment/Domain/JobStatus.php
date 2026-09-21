<?php

namespace App\Modules\Recruitment\Domain;

enum JobStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Open, self::Archived], true),
            self::Open => in_array($target, [self::Closed, self::Archived], true),
            self::Closed => in_array($target, [self::Open, self::Archived], true),
            self::Archived => false,
        };
    }
}
