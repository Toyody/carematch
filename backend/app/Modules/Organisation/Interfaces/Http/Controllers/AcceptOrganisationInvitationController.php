<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\AcceptOrganisationInvitation;
use App\Modules\Organisation\Application\Exceptions\InvitationUnavailable;
use App\Modules\Organisation\Interfaces\Http\Requests\AcceptOrganisationInvitationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AcceptOrganisationInvitationController extends Controller
{
    public function __invoke(
        AcceptOrganisationInvitationRequest $request,
        AcceptOrganisationInvitation $acceptInvitation,
    ): Response|JsonResponse {
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_int($userId)) {
            throw new LogicException('The authenticated user has no integer identifier.');
        }

        try {
            $acceptInvitation->handle(
                userId: $userId,
                rawToken: (string) $request->validated('token'),
            );
        } catch (InvitationUnavailable) {
            $message = 'The invitation is invalid or no longer available.';

            return response()->json([
                'message' => $message,
                'errors' => ['token' => [$message]],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->noContent();
    }
}
