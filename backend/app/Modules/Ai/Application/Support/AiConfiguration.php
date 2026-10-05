<?php

namespace App\Modules\Ai\Application\Support;

use App\Modules\Ai\Application\Exceptions\AiDisabled;
use App\Modules\Ai\Application\Exceptions\PermanentAiFailure;

final readonly class AiConfiguration
{
    public function assertEnabled(): void
    {
        if (config('carematch.ai.enabled') !== true) {
            throw new AiDisabled;
        }
    }

    public function provider(): string
    {
        return (string) config('carematch.ai.provider', 'openai');
    }

    public function model(): string
    {
        return (string) config('carematch.ai.model', '');
    }

    public function assertMatches(string $provider, string $model): void
    {
        if (! hash_equals($provider, $this->provider()) || ! hash_equals($model, $this->model())) {
            throw new PermanentAiFailure('provider_configuration_changed');
        }
    }
}
