<?php

namespace App\Modules\Ai\Interfaces\Http\Requests;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

abstract class AiTenantRequest extends FormRequest
{
    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);
        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function routeId(string $name): int
    {
        $id = filter_var($this->route($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new LogicException("The {$name} route identifier is invalid.");
        }

        return $id;
    }
}
