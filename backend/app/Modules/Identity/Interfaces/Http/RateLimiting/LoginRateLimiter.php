<?php

namespace App\Modules\Identity\Interfaces\Http\RateLimiting;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

final readonly class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private RateLimiter $limiter,
    ) {}

    public function tooManyAttempts(Request $request, string $email): bool
    {
        return $this->limiter->tooManyAttempts(
            $this->key($request, $email),
            self::MAX_ATTEMPTS,
        );
    }

    public function hit(Request $request, string $email): void
    {
        $this->limiter->hit(
            $this->key($request, $email),
            self::DECAY_SECONDS,
        );
    }

    public function clear(Request $request, string $email): void
    {
        $this->limiter->clear($this->key($request, $email));
    }

    public function availableIn(Request $request, string $email): int
    {
        return max(1, $this->limiter->availableIn($this->key($request, $email)));
    }

    private function key(Request $request, string $email): string
    {
        $identity = $email."\0".($request->ip() ?? 'unknown');

        return 'identity:login:'.hash('sha256', $identity);
    }
}
