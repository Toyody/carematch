<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

abstract class JobRequest extends FormRequest
{
    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);
        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function jobId(): int
    {
        $jobId = filter_var($this->route('job'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($jobId)) {
            throw new LogicException('The job route identifier is invalid.');
        }

        return $jobId;
    }
}
