<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Contracts\MatchExplanationStore;
use App\Modules\Ai\Application\Data\MatchExplanationView;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Application\Support\MatchExplanationInputFactory;
use App\Modules\Matching\Application\Contracts\MatchExplanationSource;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

final readonly class ViewMatchExplanation
{
    public function __construct(private MatchExplanationStore $store, private MatchExplanationSource $sources) {}

    public function handle(TenantContext $tenant, int $jobId, int $candidateId, int $explanationId, DateTimeImmutable $today, int $warningDays): MatchExplanationView
    {
        $explanation = $this->store->find($tenant->organisationId, $jobId, $candidateId, $explanationId)
            ?? throw new AiResourceNotFound;
        $source = $this->sources->find($tenant->organisationId, $jobId, $candidateId, $today, $warningDays);
        $stale = $source === null || ! hash_equals(
            $explanation->sourceFingerprint,
            MatchExplanationInputFactory::fromSource($source)->fingerprint(),
        );

        return new MatchExplanationView($explanation, $stale);
    }
}
