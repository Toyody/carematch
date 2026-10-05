<?php

namespace App\Modules\Ai\Application\Contracts;

use App\Modules\Ai\Application\Data\MatchExplanationInput;
use App\Modules\Ai\Application\Data\ProviderResult;

interface AiProvider
{
    public function extractCv(string $bytes, string $mimeType, string $filename): ProviderResult;

    public function explainMatch(MatchExplanationInput $input): ProviderResult;
}
