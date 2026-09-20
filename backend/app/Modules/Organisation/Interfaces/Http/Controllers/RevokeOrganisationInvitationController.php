<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\RevokeOrganisationInvitation;
use App\Modules\Organisation\Application\Exceptions\AcceptedInvitationCannotBeRevoked;
use App\Modules\Organisation\Application\Exceptions\InvitationNotFound;
use App\Modules\Organisation\Interfaces\Http\Requests\RevokeOrganisationInvitationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

final class RevokeOrganisationInvitationController extends Controller
{
    public function __invoke(
        RevokeOrganisationInvitationRequest $request,
        RevokeOrganisationInvitation $revokeInvitation,
    ): HttpResponse|JsonResponse {
        $invitationId = filter_var($request->route('invitation'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($invitationId)) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $revokeInvitation->handle($request->tenantContext(), $invitationId);
        } catch (InvitationNotFound) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        } catch (AcceptedInvitationCannotBeRevoked) {
            return response()->json([
                'message' => 'An accepted invitation cannot be revoked.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->noContent();
    }
}
