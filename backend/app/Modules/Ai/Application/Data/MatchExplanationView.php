<?php

namespace App\Modules\Ai\Application\Data;

final readonly class MatchExplanationView
{
    public function __construct(public MatchExplanationRecord $explanation, public bool $stale) {}
}
