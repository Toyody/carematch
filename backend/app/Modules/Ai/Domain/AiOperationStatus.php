<?php

namespace App\Modules\Ai\Domain;

enum AiOperationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case ReviewReady = 'review_ready';
    case Applied = 'applied';
    case Ready = 'ready';
    case Failed = 'failed';
}
