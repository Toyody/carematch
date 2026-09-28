<?php

namespace App\Modules\Compliance\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Application\Actions\ListQualificationExpiries;
use App\Modules\Compliance\Interfaces\Http\Requests\ListQualificationDefinitionsRequest;
use App\Modules\Compliance\Interfaces\Http\Resources\QualificationExpiryResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListQualificationExpiriesController extends Controller
{
    public function __invoke(ListQualificationDefinitionsRequest $request, ListQualificationExpiries $action): AnonymousResourceCollection
    {
        return QualificationExpiryResource::collection($action->handle(
            $request->tenantContext(),
            CarbonImmutable::today('UTC'),
            max(0, (int) config('carematch.compliance.expiry_warning_days', 30)),
        ));
    }
}
