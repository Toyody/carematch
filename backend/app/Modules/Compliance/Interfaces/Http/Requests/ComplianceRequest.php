<?php

namespace App\Modules\Compliance\Interfaces\Http\Requests;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

abstract class ComplianceRequest extends FormRequest
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
        $value = $this->route($name);
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new LogicException(sprintf('The %s route identifier is invalid.', $name));
        }

        return (int) $value;
    }
}
