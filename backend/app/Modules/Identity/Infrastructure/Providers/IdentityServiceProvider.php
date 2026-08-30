<?php

namespace App\Modules\Identity\Infrastructure\Providers;

use App\Modules\Identity\Interfaces\Validation\Rules\MaximumPasswordBytes;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Password::defaults(
            static fn (): Password => Password::min(12)
                ->rules([new MaximumPasswordBytes]),
        );
    }
}
