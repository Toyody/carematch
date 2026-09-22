<?php

namespace App\Modules\Candidate\Interfaces\Http\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

final readonly class CandidateDocumentFile implements ValidationRule
{
    /**
     * @param  list<string>  $allowedMimeTypes
     */
    public function __construct(
        private int $maximumBytes,
        private array $allowedMimeTypes,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The document must be a valid uploaded file.');

            return;
        }

        $size = $value->getSize();

        if (! is_int($size) || $size <= 0) {
            $fail('The document must not be empty.');

            return;
        }

        if ($size > $this->maximumBytes) {
            $fail('The document must not be larger than 10 MiB.');

            return;
        }

        try {
            $mimeType = $value->getMimeType();
        } catch (Throwable) {
            $mimeType = null;
        }

        if (! is_string($mimeType) || ! in_array($mimeType, $this->allowedMimeTypes, true)) {
            $fail('The document must be a PDF or DOCX file.');
        }
    }
}
