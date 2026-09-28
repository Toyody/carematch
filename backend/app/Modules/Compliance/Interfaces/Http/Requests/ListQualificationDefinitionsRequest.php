<?php

namespace App\Modules\Compliance\Interfaces\Http\Requests;

use App\Modules\Compliance\Interfaces\Authorization\CompliancePolicy;
use Illuminate\Support\Facades\Gate;

final class ListQualificationDefinitionsRequest extends ComplianceRequest
{
    public function authorize(): bool
    {
        return Gate::allows(CompliancePolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
