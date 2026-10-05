<?php

namespace App\Modules\Compliance\Domain;

enum ExpiryDigestStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
}
