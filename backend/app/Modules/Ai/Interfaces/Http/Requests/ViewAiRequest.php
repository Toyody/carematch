<?php

namespace App\Modules\Ai\Interfaces\Http\Requests;

use App\Modules\Ai\Interfaces\Authorization\AiPolicy;
use Illuminate\Support\Facades\Gate;

final class ViewAiRequest extends AiTenantRequest
{
    public function authorize(): bool
    {
        return Gate::allows(AiPolicy::VIEW, $this->tenantContext());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
