<?php

namespace App\Modules\Ai\Application\Contracts;

interface AiRequestDispatcher
{
    public function dispatchCvExtractionAfterCommit(int $requestId): void;

    public function dispatchMatchExplanationAfterCommit(int $requestId): void;
}
