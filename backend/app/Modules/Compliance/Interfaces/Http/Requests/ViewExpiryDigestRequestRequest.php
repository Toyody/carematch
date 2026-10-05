<?php

namespace App\Modules\Compliance\Interfaces\Http\Requests;

use App\Modules\Compliance\Interfaces\Authorization\CompliancePolicy;
use Illuminate\Support\Facades\Gate;

final class ViewExpiryDigestRequestRequest extends ComplianceRequest
{
    public function authorize(): bool
    {
        return Gate::allows(CompliancePolicy::REQUEST_EXPIRY_DIGEST, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
