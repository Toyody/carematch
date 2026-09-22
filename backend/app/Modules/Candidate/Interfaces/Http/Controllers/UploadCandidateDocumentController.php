<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\UploadCandidateDocument;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentPersistenceFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Candidate\Application\Support\SafeDocumentFilename;
use App\Modules\Candidate\Interfaces\Http\Requests\UploadCandidateDocumentRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateDocumentResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UploadCandidateDocumentController extends Controller
{
    public function __invoke(
        UploadCandidateDocumentRequest $request,
        SafeDocumentFilename $filenames,
        UploadCandidateDocument $uploadDocument,
    ): JsonResponse {
        try {
            $document = $uploadDocument->handle(
                $request->tenantContext(),
                $request->candidateId(),
                $request->documentUpload($filenames),
            );
        } catch (CandidateNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (CandidateDocumentStorageFailure|CandidateDocumentPersistenceFailure) {
            return response()->json(
                ['message' => 'The document could not be stored. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return (new CandidateDocumentResource($document))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
