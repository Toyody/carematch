<?php

namespace App\Modules\Ai\Infrastructure\Jobs;

use App\Modules\Ai\Application\Contracts\AiProvider;
use App\Modules\Ai\Application\Contracts\CvExtractionStore;
use App\Modules\Ai\Application\Data\CvExtractionDraft;
use App\Modules\Ai\Application\Exceptions\PermanentAiFailure;
use App\Modules\Ai\Application\Exceptions\RetryableAiFailure;
use App\Modules\Ai\Application\Support\AiConfiguration;
use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStorage;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use App\Modules\Candidate\Application\Support\CandidateVersionFingerprint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

final class ProcessCvExtractionJob implements ShouldQueue
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
        return [(new WithoutOverlapping('ai-cv-extraction:'.$this->requestId))->shared()->releaseAfter(10)->expireAfter(180)];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(CvExtractionStore $store, CandidateDocumentStore $documents, CandidateDocumentStorage $storage, CandidateDetails $candidates, AiProvider $provider, AiConfiguration $configuration): void
    {
        $attempt = $this->attempts();
        $work = $store->beginProcessing($this->requestId);
        if ($work === null) {
            return;
        }
        try {
            $configuration->assertMatches($work->provider, $work->model);
            $document = $documents->find($work->organisationId, $work->candidateId, $work->candidateDocumentId)
                ?? throw new PermanentAiFailure('source_document_missing');
            $candidate = $candidates->find($work->organisationId, $work->candidateId)
                ?? throw new PermanentAiFailure('source_candidate_missing');
            try {
                $bytes = $storage->get($document->storageKey);
            } catch (CandidateDocumentStorageFailure) {
                throw new RetryableAiFailure('Candidate document storage is temporarily unavailable.');
            }
            $result = $provider->extractCv($bytes, $document->mimeType, $document->originalName);
            if (! $result->value instanceof CvExtractionDraft) {
                throw new PermanentAiFailure('invalid_provider_response');
            }
            $store->markReviewReady($work->id, $result->value, $candidate->updatedAt, CandidateVersionFingerprint::for($candidate), $result->requestId);
            Log::info('AI CV extraction is ready for review.', ['request_id' => $work->id, 'attempt' => $attempt]);
        } catch (PermanentAiFailure $exception) {
            $store->markFailed($work->id, $exception->failureCode);
            Log::warning('AI CV extraction permanently failed.', ['request_id' => $work->id, 'attempt' => $attempt, 'failure_code' => $exception->failureCode]);
        } catch (RetryableAiFailure) {
            $maximumReceives = max(1, (int) config('carematch.ai.max_receive_count', 3));
            if ($attempt >= $maximumReceives) {
                $store->markFailed($work->id, 'delivery_attempts_exhausted');
            }
            Log::warning('AI CV extraction will be retried.', ['request_id' => $work->id, 'attempt' => $attempt]);
            throw new RetryableAiFailure('AI CV extraction temporarily failed.');
        }
    }
}
