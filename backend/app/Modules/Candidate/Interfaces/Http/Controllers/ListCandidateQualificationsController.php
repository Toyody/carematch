<?php

namespace App\Modules\Candidate\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Application\Actions\ListCandidateQualifications;
use App\Modules\Candidate\Application\Exceptions\CandidateQualificationNotFound;
use App\Modules\Candidate\Interfaces\Http\Requests\CandidateQualificationRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateQualificationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListCandidateQualificationsController extends Controller
{
    public function __invoke(CandidateQualificationRequest $request, ListCandidateQualifications $action): AnonymousResourceCollection
    {
        try {
            return CandidateQualificationResource::collection($action->handle($request->tenantContext(), $request->routeId('candidate')));
        } catch (CandidateQualificationNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
