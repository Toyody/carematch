<?php

namespace App\Modules\Ai\Application\Data;

final readonly class MatchExplanationDraft
{
    /** @param list<array{type: string, explanation: string}> $factors */
    public function __construct(public string $summary, public array $factors) {}
}
