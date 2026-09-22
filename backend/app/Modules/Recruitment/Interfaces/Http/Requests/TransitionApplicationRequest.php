<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Interfaces\Authorization\ApplicationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use LogicException;

final class TransitionApplicationRequest extends ApplicationRequest
{
    protected function prepareForValidation(): void
    {
        $note = $this->input('note');
        if (is_string($note)) {
            $note = trim($note);
            $this->merge(['note' => $note === '' ? null : $note]);
        }
    }

    public function authorize(): bool
    {
        return Gate::allows(ApplicationPolicy::TRANSITION, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::enum(ApplicationStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'organisation_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'membership_id' => ['prohibited'],
            'changed_by_user_id' => ['prohibited'],
            'application_id' => ['prohibited'],
            'from_status' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }

    public function targetStatus(): ApplicationStatus
    {
        $status = $this->validated('to_status');
        if (! is_string($status)) {
            throw new LogicException('The validated target status is invalid.');
        }

        return ApplicationStatus::from($status);
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }
}
