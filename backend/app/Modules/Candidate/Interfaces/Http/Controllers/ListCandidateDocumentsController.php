<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\ListCandidateDocuments;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\ListCandidateDocumentsRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateDocumentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListCandidateDocumentsController extends Controller
{
    public function __invoke(
        ListCandidateDocumentsRequest $request,
        ListCandidateDocuments $listDocuments,
    ): AnonymousResourceCollection {
        try {
            return CandidateDocumentResource::collection(
                $listDocuments->handle($request->tenantContext(), $request->candidateId()),
            );
        } catch (CandidateNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
