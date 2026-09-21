<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use Illuminate\Support\Facades\Gate;

final class ShowJobRequest extends JobRequest
{
    public function authorize(): bool
    {
        return Gate::allows(JobPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
