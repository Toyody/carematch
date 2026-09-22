<?php

namespace App\Modules\Candidate\Infrastructure\Storage;

use App\Modules\Candidate\Application\Contracts\CandidateDocumentStorage;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

final readonly class LaravelCandidateDocumentStorage implements CandidateDocumentStorage
{
    public function __construct(private FilesystemManager $filesystems) {}

    public function exists(string $storageKey): bool
    {
        try {
            return $this->disk()->exists($storageKey);
        } catch (Throwable) {
            throw new CandidateDocumentStorageFailure;
        }
    }

    public function put(string $sourcePath, string $storageKey): void
    {
        $stream = @fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new CandidateDocumentStorageFailure;
        }

        try {
            if (! $this->disk()->writeStream($storageKey, $stream)) {
                throw new CandidateDocumentStorageFailure;
            }
        } catch (CandidateDocumentStorageFailure $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new CandidateDocumentStorageFailure;
        } finally {
            fclose($stream);
        }
    }

    public function get(string $storageKey): string
    {
        try {
            if (! $this->disk()->exists($storageKey)) {
                throw new CandidateDocumentStorageFailure;
            }

            $contents = $this->disk()->get($storageKey);

            if (! is_string($contents)) {
                throw new CandidateDocumentStorageFailure;
            }

            return $contents;
        } catch (CandidateDocumentStorageFailure $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new CandidateDocumentStorageFailure;
        }
    }

    public function delete(string $storageKey): void
    {
        try {
            if ($this->disk()->exists($storageKey) && ! $this->disk()->delete($storageKey)) {
                throw new CandidateDocumentStorageFailure;
            }
        } catch (CandidateDocumentStorageFailure $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new CandidateDocumentStorageFailure;
        }
    }

    private function disk(): Filesystem
    {
        $disk = config('carematch.candidate_documents.disk');

        if (! is_string($disk) || $disk === '') {
            throw new CandidateDocumentStorageFailure;
        }

        return $this->filesystems->disk($disk);
    }
}
