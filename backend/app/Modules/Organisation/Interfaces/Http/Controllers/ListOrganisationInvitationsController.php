<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\ListOrganisationInvitations;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationInvitationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class ListOrganisationInvitationsController extends Controller
{
    public function __invoke(
        Request $request,
        ListOrganisationInvitations $listInvitations,
    ): AnonymousResourceCollection {
        $tenant = $request->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        Gate::authorize('manageInvitations', $tenant);

        return OrganisationInvitationResource::collection(
            $listInvitations->handle($tenant),
        );
    }
}
