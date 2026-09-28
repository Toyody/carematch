<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\UpdateQualificationDefinition;
use App\Modules\Compliance\Application\Exceptions\QualificationDefinitionNotFound;
use App\Modules\Compliance\Interfaces\Http\Requests\UpdateQualificationDefinitionRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\QualificationDefinitionResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateQualificationDefinitionController extends Controller
{
    public function __invoke(UpdateQualificationDefinitionRequest $request, UpdateQualificationDefinition $action): QualificationDefinitionResource
    {
        try {
            return new QualificationDefinitionResource($action->handle(
                $request->tenantContext(),
                $request->routeId('qualification'),
                $request->definitionData(),
            ));
        } catch (QualificationDefinitionNotFound) {
            throw new NotFoundHttpException('Not Found');
        }
    }
}
