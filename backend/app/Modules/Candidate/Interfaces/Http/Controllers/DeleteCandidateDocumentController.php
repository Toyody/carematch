<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\DeleteCandidateDocument;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentNotFound;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentPersistenceFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\DeleteCandidateDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteCandidateDocumentController extends Controller
{
    public function __invoke(
        DeleteCandidateDocumentRequest $request,
        DeleteCandidateDocument $deleteDocument,
    ): HttpResponse|JsonResponse {
        try {
            $deleteDocument->handle(
                $request->tenantContext(),
                $request->candidateId(),
                $request->documentId(),
            );
        } catch (CandidateNotFound|CandidateDocumentNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (CandidateDocumentStorageFailure|CandidateDocumentPersistenceFailure) {
            return response()->json(
                ['message' => 'The document could not be deleted. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return response()->noContent();
    }
}
