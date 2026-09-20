<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\ChangeOrganisationMembershipRole;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Exceptions\DeactivatedMembershipRoleConflict;
use App\Modules\Organisation\Application\Exceptions\LastActiveAdminConflict;
use App\Modules\Organisation\Application\Exceptions\MembershipManagementForbidden;
use App\Modules\Organisation\Application\Exceptions\OrganisationMembershipNotFound;
use App\Modules\Organisation\Application\Exceptions\TenantMembershipUnavailable;
use App\Modules\Organisation\Interfaces\Http\Requests\ChangeOrganisationMembershipRoleRequest;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationMembershipResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ChangeOrganisationMembershipRoleController extends Controller
{
    public function __invoke(
        ChangeOrganisationMembershipRoleRequest $request,
        ChangeOrganisationMembershipRole $changeRole,
    ): OrganisationMembershipResource|JsonResponse {
        try {
            $membership = $changeRole->handle(
                tenant: $request->tenantContext(),
                membershipId: $request->membershipId(),
                role: OrganisationRole::from((string) $request->validated('role')),
            );
        } catch (OrganisationMembershipNotFound|TenantMembershipUnavailable) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        } catch (MembershipManagementForbidden) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], Response::HTTP_FORBIDDEN);
        } catch (DeactivatedMembershipRoleConflict) {
            return response()->json([
                'message' => 'A deactivated membership role cannot be changed.',
            ], Response::HTTP_CONFLICT);
        } catch (LastActiveAdminConflict) {
            return response()->json([
                'message' => 'An organisation must retain at least one active Admin.',
            ], Response::HTTP_CONFLICT);
        }

        return new OrganisationMembershipResource($membership);
    }
}
