<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

abstract class ApplicationRequest extends FormRequest
{
    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);
        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function applicationId(): int
    {
        $id = filter_var($this->route('application'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($id)) {
            throw new LogicException('The application route identifier is invalid.');
        }

        return $id;
    }
}
