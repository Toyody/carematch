<?php

namespace App\Modules\Ai\Interfaces\Http\Requests;

use App\Modules\Ai\Interfaces\Authorization\AiPolicy;
use Illuminate\Support\Facades\Gate;

final class ViewCvExtractionRequest extends AiTenantRequest
{
    public function authorize(): bool
    {
        return Gate::allows(AiPolicy::USE, $this->tenantContext());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
