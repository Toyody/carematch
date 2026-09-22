<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\DownloadCandidateDocument;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentNotFound;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\DownloadCandidateDocumentRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadCandidateDocumentController extends Controller
{
    public function __invoke(
        DownloadCandidateDocumentRequest $request,
        DownloadCandidateDocument $downloadDocument,
    ): StreamedResponse|JsonResponse {
        try {
            $download = $downloadDocument->handle(
                $request->tenantContext(),
                $request->candidateId(),
                $request->documentId(),
            );
        } catch (CandidateNotFound|CandidateDocumentNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (CandidateDocumentStorageFailure) {
            return response()->json(
                ['message' => 'The document is temporarily unavailable.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return response()->streamDownload(
            static function () use ($download): void {
                echo $download->contents;
            },
            $download->document->originalName,
            [
                'Content-Type' => $download->document->mimeType,
                'Content-Length' => (string) $download->document->sizeBytes,
            ],
        );
    }
}
