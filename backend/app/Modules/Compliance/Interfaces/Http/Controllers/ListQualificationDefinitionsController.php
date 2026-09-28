<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\ListQualificationDefinitions;
use App\Modules\Compliance\Interfaces\Http\Requests\ListQualificationDefinitionsRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\QualificationDefinitionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListQualificationDefinitionsController extends Controller
{
    public function __invoke(ListQualificationDefinitionsRequest $request, ListQualificationDefinitions $action): AnonymousResourceCollection
    {
        return QualificationDefinitionResource::collection($action->handle($request->tenantContext()));
    }
}
