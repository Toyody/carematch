<?php

namespace App\Modules\Candidate\Interfaces\Http\Requests;

use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class DownloadCandidateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows(CandidatePolicy::VIEW_DOCUMENTS, $tenant);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }

    public function tenantContext(): TenantContext
    {
        $tenant = $this->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return $tenant;
    }

    public function candidateId(): int
    {
        return $this->routeIdentifier('candidate');
    }

    public function documentId(): int
    {
        return $this->routeIdentifier('document');
    }

    private function routeIdentifier(string $name): int
    {
        $identifier = filter_var($this->route($name), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($identifier)) {
            throw new LogicException("The {$name} route identifier is invalid.");
        }

        return $identifier;
    }
}
