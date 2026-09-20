<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\CreateOrganisation;
use App\Modules\Organisation\Interfaces\Http\Requests\CreateOrganisationRequest;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationResource;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class CreateOrganisationController extends Controller
{
    public function __invoke(
        CreateOrganisationRequest $request,
        CreateOrganisation $createOrganisation,
    ): JsonResponse {
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_int($userId)) {
            throw new LogicException('The authenticated user has no integer identifier.');
        }

        $organisation = $createOrganisation->handle(
            name: (string) $request->validated('name'),
            creatorUserId: $userId,
        );

        return (new OrganisationResource($organisation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
