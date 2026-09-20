<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\DeactivateOrganisationMembership;
use App\Modules\Organisation\Application\Exceptions\LastActiveAdminConflict;
use App\Modules\Organisation\Application\Exceptions\MembershipManagementForbidden;
use App\Modules\Organisation\Application\Exceptions\OrganisationMembershipNotFound;
use App\Modules\Organisation\Application\Exceptions\TenantMembershipUnavailable;
use App\Modules\Organisation\Interfaces\Http\Requests\DeactivateOrganisationMembershipRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

final class DeactivateOrganisationMembershipController extends Controller
{
    public function __invoke(
        DeactivateOrganisationMembershipRequest $request,
        DeactivateOrganisationMembership $deactivateMembership,
    ): HttpResponse|JsonResponse {
        try {
            $deactivateMembership->handle(
                tenant: $request->tenantContext(),
                membershipId: $request->membershipId(),
            );
        } catch (OrganisationMembershipNotFound|TenantMembershipUnavailable) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        } catch (MembershipManagementForbidden) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], Response::HTTP_FORBIDDEN);
        } catch (LastActiveAdminConflict) {
            return response()->json([
                'message' => 'An organisation must retain at least one active Admin.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->noContent();
    }
}
