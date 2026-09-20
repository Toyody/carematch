<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\UpdateOrganisation;
use App\Modules\Organisation\Interfaces\Http\Requests\UpdateOrganisationRequest;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationResource;

final class UpdateOrganisationController extends Controller
{
    public function __invoke(
        UpdateOrganisationRequest $request,
        UpdateOrganisation $updateOrganisation,
    ): OrganisationResource {
        $organisation = $updateOrganisation->handle(
            tenant: $request->tenantContext(),
            name: (string) $request->validated('name'),
        );

        return new OrganisationResource($organisation);
    }
}
