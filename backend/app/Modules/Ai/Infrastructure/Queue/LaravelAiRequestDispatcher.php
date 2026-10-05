<?php

namespace App\Modules\Ai\Infrastructure\Queue;

use App\Modules\Ai\Application\Contracts\AiRequestDispatcher;
use App\Modules\Ai\Infrastructure\Jobs\GenerateMatchExplanationJob;
use App\Modules\Ai\Infrastructure\Jobs\ProcessCvExtractionJob;

final class LaravelAiRequestDispatcher implements AiRequestDispatcher
{
    public function dispatchCvExtractionAfterCommit(int $requestId): void
    {
        ProcessCvExtractionJob::dispatch($requestId)->afterCommit();
    }

    public function dispatchMatchExplanationAfterCommit(int $requestId): void
    {
        GenerateMatchExplanationJob::dispatch($requestId)->afterCommit();
    }
}
