<?php

namespace App\Modules\Ai\Application\Data;

final readonly class ProviderResult
{
    public function __construct(public mixed $value, public ?string $requestId = null) {}
}
