<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\ListOrganisationMemberships;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Application\Exceptions\MembershipManagementForbidden;
use App\Modules\Organisation\Application\Exceptions\TenantMembershipUnavailable;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationMembershipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ListOrganisationMembershipsController extends Controller
{
    public function __invoke(
        Request $request,
        ListOrganisationMemberships $listMemberships,
    ): AnonymousResourceCollection|JsonResponse {
        $tenant = $request->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        Gate::authorize('manageMemberships', $tenant);

        try {
            $memberships = $listMemberships->handle($tenant);
        } catch (TenantMembershipUnavailable) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        } catch (MembershipManagementForbidden) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], Response::HTTP_FORBIDDEN);
        }

        return OrganisationMembershipResource::collection($memberships);
    }
}
