<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\ViewOrganisation;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class ShowOrganisationController extends Controller
{
    public function __invoke(
        Request $request,
        ViewOrganisation $viewOrganisation,
    ): OrganisationResource {
        $tenant = $request->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        Gate::authorize('view', $tenant);

        return new OrganisationResource($viewOrganisation->handle($tenant));
    }
}
