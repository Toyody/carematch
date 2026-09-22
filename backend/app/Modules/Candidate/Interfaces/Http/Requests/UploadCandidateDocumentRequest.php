<?php

namespace App\Modules\Candidate\Interfaces\Http\Requests;

use App\Modules\Candidate\Application\Data\CandidateDocumentUpload;
use App\Modules\Candidate\Application\Support\SafeDocumentFilename;
use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Candidate\Interfaces\Http\Validation\CandidateDocumentFile;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class UploadCandidateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get(TenantContext::class);

        return $tenant instanceof TenantContext
            && Gate::allows(CandidatePolicy::MANAGE_DOCUMENTS, $tenant);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $maximumBytes = config('carematch.candidate_documents.max_bytes');
        $allowedMimeTypes = config('carematch.candidate_documents.allowed_mime_types');

        if (! is_int($maximumBytes) || ! is_array($allowedMimeTypes)) {
            throw new LogicException('Candidate document validation is not configured.');
        }

        $mimeTypes = array_values(array_filter(
            $allowedMimeTypes,
            static fn (mixed $mimeType): bool => is_string($mimeType),
        ));

        return [
            'document' => [
                'required',
                'file',
                new CandidateDocumentFile($maximumBytes, $mimeTypes),
            ],
            'id' => ['prohibited'],
            'organisation_id' => ['prohibited'],
            'candidate_id' => ['prohibited'],
            'original_name' => ['prohibited'],
            'storage_key' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'size_bytes' => ['prohibited'],
            'uploaded_by_user_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
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
        $candidateId = filter_var($this->route('candidate'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($candidateId)) {
            throw new LogicException('The candidate route identifier is invalid.');
        }

        return $candidateId;
    }

    public function documentUpload(SafeDocumentFilename $filenames): CandidateDocumentUpload
    {
        $file = $this->file('document');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('The validated document upload is missing.');
        }

        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        if (! is_string($mimeType) || ! is_int($size)) {
            throw new LogicException('The validated document metadata is unavailable.');
        }

        return new CandidateDocumentUpload(
            temporaryPath: $file->getPathname(),
            originalName: $filenames->fromClientName($file->getClientOriginalName()),
            mimeType: $mimeType,
            sizeBytes: $size,
        );
    }
}
