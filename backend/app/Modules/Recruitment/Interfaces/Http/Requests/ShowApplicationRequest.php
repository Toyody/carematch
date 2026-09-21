<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Interfaces\Authorization\ApplicationPolicy;
use Illuminate\Support\Facades\Gate;

final class ShowApplicationRequest extends ApplicationRequest
{
    public function authorize(): bool
    {
        return Gate::allows(ApplicationPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
