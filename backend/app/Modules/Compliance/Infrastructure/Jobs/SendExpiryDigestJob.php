<?php

namespace App\Modules\Compliance\Infrastructure\Jobs;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestNotifier;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestRequestStore;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestSummaryReadModel;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestDeliveryException;
use App\Modules\Compliance\Application\Exceptions\PermanentExpiryDigestFailure;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendExpiryDigestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 60;

    public bool $failOnTimeout = false;

    public function __construct(public readonly int $requestId)
    {
        $this->onQueue((string) config('carematch.compliance.expiry_digest.queue', 'compliance'));
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('compliance-expiry-digest:'.$this->requestId))
                ->shared()
                ->releaseAfter(5)
                ->expireAfter(120),
        ];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [2, 10];
    }

    public function handle(
        ExpiryDigestRequestStore $store,
        ExpiryDigestSummaryReadModel $summaries,
        ExpiryDigestNotifier $notifier,
    ): void {
        $attempt = $this->attempts();
        $work = $store->beginProcessing($this->requestId);
        if ($work === null) {
            Log::info('Expiry digest job safely skipped.', [
                'request_id' => $this->requestId,
                'attempt' => $attempt,
            ]);

            return;
        }

        try {
            $warningDays = max(0, (int) config('carematch.compliance.expiry_warning_days', 30));
            $summary = $summaries->forOrganisation(
                $work->organisationId,
                CarbonImmutable::today('UTC'),
                $warningDays,
            );
            $notifier->send($work->organisationId, $work->requestedByUserId, $summary);
            $store->markSent($work->requestId, $summary);

            Log::info('Expiry digest delivered.', [
                'request_id' => $this->requestId,
                'attempt' => $attempt,
            ]);
        } catch (PermanentExpiryDigestFailure $exception) {
            $store->markFailed($this->requestId, $exception->failureCode);
            Log::warning('Expiry digest permanently rejected.', [
                'request_id' => $this->requestId,
                'attempt' => $attempt,
                'failure_code' => $exception->failureCode,
            ]);
        } catch (Throwable) {
            $maximumReceives = max(1, (int) config('carematch.compliance.expiry_digest.max_receive_count', 3));
            if ($attempt >= $maximumReceives) {
                $store->markFailed($this->requestId, 'delivery_attempts_exhausted');
            }

            Log::warning('Expiry digest delivery will be retried.', [
                'request_id' => $this->requestId,
                'attempt' => $attempt,
            ]);

            throw new ExpiryDigestDeliveryException('Expiry digest delivery failed.');
        }
    }
}
