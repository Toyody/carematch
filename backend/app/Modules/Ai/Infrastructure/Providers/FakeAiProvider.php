<?php

namespace App\Modules\Ai\Infrastructure\Providers;

use App\Modules\Ai\Application\Contracts\AiProvider;
use App\Modules\Ai\Application\Data\CvExtractionDraft;
use App\Modules\Ai\Application\Data\MatchExplanationDraft;
use App\Modules\Ai\Application\Data\MatchExplanationInput;
use App\Modules\Ai\Application\Data\ProviderResult;
use App\Modules\Ai\Application\Exceptions\PermanentAiFailure;
use App\Modules\Ai\Application\Exceptions\RetryableAiFailure;

final class FakeAiProvider implements AiProvider
{
    public function extractCv(string $bytes, string $mimeType, string $filename): ProviderResult
    {
        if (str_contains($bytes, 'FAKE_AI_RETRYABLE')) {
            throw new RetryableAiFailure('Synthetic retryable provider failure.');
        }
        if (str_contains($bytes, 'FAKE_AI_PERMANENT')) {
            throw new PermanentAiFailure('provider_rejected_request');
        }
        if (str_contains($bytes, 'FAKE_AI_NULL_FIELDS')) {
            return new ProviderResult(new CvExtractionDraft(null, null, null, null, null, null), 'fake-cv-request');
        }

        return new ProviderResult(new CvExtractionDraft('Synthetic', 'Candidate', 'synthetic.candidate@example.test', '+61 400 000 000', 'Registered Nurse', 'Melbourne'), 'fake-cv-request');
    }

    public function explainMatch(MatchExplanationInput $input): ProviderResult
    {
        if (str_contains($input->jobTitle, 'FAKE_AI_RETRYABLE')) {
            throw new RetryableAiFailure('Synthetic retryable provider failure.');
        }

        return new ProviderResult(new MatchExplanationDraft(
            'This explanation summarises the deterministic match factors and does not affect ranking.',
            [
                ['type' => 'qualification', 'explanation' => 'Qualification requirements are represented by the supplied deterministic status.'],
                ['type' => 'occupation', 'explanation' => 'Occupation alignment is represented by the supplied deterministic status.'],
            ],
        ), 'fake-match-request');
    }
}
