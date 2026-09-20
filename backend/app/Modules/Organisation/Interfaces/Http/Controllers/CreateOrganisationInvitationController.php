<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\CreateOrganisationInvitation;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Exceptions\ActiveMemberCannotBeInvited;
use App\Modules\Organisation\Application\Exceptions\PendingInvitationExists;
use App\Modules\Organisation\Interfaces\Http\Requests\CreateOrganisationInvitationRequest;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationInvitationResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateOrganisationInvitationController extends Controller
{
    public function __invoke(
        CreateOrganisationInvitationRequest $request,
        CreateOrganisationInvitation $createInvitation,
    ): JsonResponse {
        try {
            $invitation = $createInvitation->handle(
                tenant: $request->tenantContext(),
                email: (string) $request->validated('email'),
                role: OrganisationRole::from((string) $request->validated('role')),
            );
        } catch (ActiveMemberCannotBeInvited) {
            return response()->json([
                'message' => 'The user is already an active member of this organisation.',
            ], Response::HTTP_CONFLICT);
        } catch (PendingInvitationExists) {
            return response()->json([
                'message' => 'An unresolved invitation already exists for this email.',
            ], Response::HTTP_CONFLICT);
        }

        return (new OrganisationInvitationResource($invitation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
