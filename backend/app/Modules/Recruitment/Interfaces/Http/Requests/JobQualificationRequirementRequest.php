<?php

namespace App\Modules\Recruitment\Interfaces\Http\Requests;

use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use Illuminate\Support\Facades\Gate;

final class JobQualificationRequirementRequest extends JobRequest
{
    public function authorize(): bool
    {
        $ability = in_array($this->method(), ['GET', 'HEAD'], true)
            ? JobPolicy::VIEW_QUALIFICATION_REQUIREMENTS
            : JobPolicy::MANAGE_QUALIFICATION_REQUIREMENTS;

        return Gate::allows($ability, $this->tenantContext());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return $this->isMethod('POST') ? [
            'qualification_definition_id' => ['required', 'integer', 'min:1'],
            'organisation_id' => ['prohibited'], 'job_id' => ['prohibited'], 'id' => ['prohibited'],
        ] : [];
    }

    public function routeId(string $name): int
    {
        $value = $this->route($name);
        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            throw new \LogicException('Invalid route identifier.');
        }

        return (int) $value;
    }
}
