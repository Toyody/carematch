<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Contracts\AiRequestDispatcher;
use App\Modules\Ai\Application\Contracts\MatchExplanationStore;
use App\Modules\Ai\Application\Data\MatchExplanationRecord;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Application\Support\AiConfiguration;
use App\Modules\Ai\Application\Support\MatchExplanationInputFactory;
use App\Modules\Matching\Application\Contracts\MatchExplanationSource;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

final readonly class RequestMatchExplanation
{
    public const string PROMPT_VERSION = 'match_explanation_prompt_v1';

    public const string SCHEMA_VERSION = 'match_explanation_schema_v1';

    public function __construct(
        private AiConfiguration $configuration,
        private MatchExplanationSource $sources,
        private MatchExplanationStore $explanations,
        private AiRequestDispatcher $dispatcher,
    ) {}

    public function handle(TenantContext $tenant, int $jobId, int $candidateId, DateTimeImmutable $today, int $warningDays): MatchExplanationRecord
    {
        $this->configuration->assertEnabled();
        $source = $this->sources->find($tenant->organisationId, $jobId, $candidateId, $today, $warningDays)
            ?? throw new AiResourceNotFound;
        $input = MatchExplanationInputFactory::fromSource($source);
        $record = $this->explanations->createOrRetrieve(
            $tenant, $jobId, $candidateId, $input->fingerprint(), $this->configuration->provider(),
            $this->configuration->model(), self::PROMPT_VERSION, self::SCHEMA_VERSION,
        );
        if ($record->status->value === 'queued') {
            $this->dispatcher->dispatchMatchExplanationAfterCommit($record->id);
        }

        return $record;
    }
}
