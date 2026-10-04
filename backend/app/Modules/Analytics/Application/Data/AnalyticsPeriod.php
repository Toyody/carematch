<?php

namespace App\Modules\Analytics\Application\Data;

use DateTimeImmutable;

final readonly class AnalyticsPeriod
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {}

    public function start(): DateTimeImmutable
    {
        return $this->from->setTime(0, 0);
    }

    public function endExclusive(): DateTimeImmutable
    {
        return $this->to->modify('+1 day')->setTime(0, 0);
    }
}
