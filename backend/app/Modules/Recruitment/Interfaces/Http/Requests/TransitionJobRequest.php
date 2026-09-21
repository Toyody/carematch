<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Domain\JobStatus;
use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class TransitionJobRequest extends JobRequest
{
    public function authorize(): bool
    {
        return Gate::allows(JobPolicy::TRANSITION, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
        ];
    }

    public function targetStatus(): JobStatus
    {
        $transition = $this->route('transition');
        if (! is_string($transition)) {
            throw new LogicException('The lifecycle transition is invalid.');
        }

        return JobStatus::from($transition);
    }
}
