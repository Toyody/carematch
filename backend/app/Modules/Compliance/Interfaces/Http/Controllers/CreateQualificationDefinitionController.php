<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\CreateQualificationDefinition;
use App\Modules\Compliance\Interfaces\Http\Requests\StoreQualificationDefinitionRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\QualificationDefinitionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateQualificationDefinitionController extends Controller
{
    public function __invoke(StoreQualificationDefinitionRequest $request, CreateQualificationDefinition $action): JsonResponse
    {
        return (new QualificationDefinitionResource($action->handle($request->tenantContext(), $request->definitionData())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
