<?php

use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
];
