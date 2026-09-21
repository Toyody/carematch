<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Interfaces\Authorization\ApplicationPolicy;
use Illuminate\Support\Facades\Gate;

final class CreateApplicationRequest extends ApplicationRequest
{
    public function authorize(): bool
    {
        return Gate::allows(ApplicationPolicy::CREATE, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'min:1'],
            'candidate_id' => ['required', 'integer', 'min:1'],
            'id' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'status' => ['prohibited'],
            'applied_at' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function jobId(): int
    {
        return (int) $this->validated('job_id');
    }

    public function candidateId(): int
    {
        return (int) $this->validated('candidate_id');
    }
}
