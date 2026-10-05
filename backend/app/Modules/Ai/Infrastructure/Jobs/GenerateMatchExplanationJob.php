<?php

namespace App\Modules\Ai\Infrastructure\Jobs;

use App\Modules\Ai\Application\Contracts\AiProvider;
use App\Modules\Ai\Application\Contracts\MatchExplanationStore;
use App\Modules\Ai\Application\Data\MatchExplanationDraft;
use App\Modules\Ai\Application\Exceptions\PermanentAiFailure;
use App\Modules\Ai\Application\Exceptions\RetryableAiFailure;
use App\Modules\Ai\Application\Support\AiConfiguration;
use App\Modules\Ai\Application\Support\MatchExplanationInputFactory;
use App\Modules\Matching\Application\Contracts\MatchExplanationSource;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

final class GenerateMatchExplanationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 120;

    public bool $failOnTimeout = false;

    public function __construct(public readonly int $requestId)
    {
        $this->onQueue((string) config('carematch.ai.queue', 'ai'));
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('ai-match-explanation:'.$this->requestId))->shared()->releaseAfter(10)->expireAfter(180)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(MatchExplanationStore $store, MatchExplanationSource $sources, AiProvider $provider, AiConfiguration $configuration): void
    {
        $attempt = $this->attempts();
        $work = $store->beginProcessing($this->requestId);
        if ($work === null) {
            return;
        }
        try {
            $configuration->assertMatches($work->provider, $work->model);
            $source = $sources->find($work->organisationId, $work->jobId, $work->candidateId, CarbonImmutable::today('UTC'), (int) config('carematch.compliance.expiry_warning_days', 30))
                ?? throw new PermanentAiFailure('source_match_missing');
            $input = MatchExplanationInputFactory::fromSource($source);
            if (! hash_equals($work->sourceFingerprint, $input->fingerprint())) {
                throw new PermanentAiFailure('source_match_changed');
            }
            $result = $provider->explainMatch($input);
            if (! $result->value instanceof MatchExplanationDraft) {
                throw new PermanentAiFailure('invalid_provider_response');
            }
            $store->markReady($work->id, $result->value, $result->requestId);
            Log::info('AI match explanation is ready.', ['request_id' => $work->id, 'attempt' => $attempt]);
        } catch (PermanentAiFailure $exception) {
            $store->markFailed($work->id, $exception->failureCode);
            Log::warning('AI match explanation permanently failed.', ['request_id' => $work->id, 'attempt' => $attempt, 'failure_code' => $exception->failureCode]);
        } catch (RetryableAiFailure) {
            $maximumReceives = max(1, (int) config('carematch.ai.max_receive_count', 3));
            if ($attempt >= $maximumReceives) {
                $store->markFailed($work->id, 'delivery_attempts_exhausted');
            }
            Log::warning('AI match explanation will be retried.', ['request_id' => $work->id, 'attempt' => $attempt]);
            throw new RetryableAiFailure('AI match explanation temporarily failed.');
        }
    }
}
