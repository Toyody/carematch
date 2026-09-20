<?php

namespace App\Modules\Organisation\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organisation\Application\Actions\ListOrganisations;
use App\Modules\Organisation\Interfaces\Http\Resources\OrganisationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use LogicException;

final class ListOrganisationsController extends Controller
{
    public function __invoke(
        Request $request,
        ListOrganisations $listOrganisations,
    ): AnonymousResourceCollection {
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_int($userId)) {
            throw new LogicException('The authenticated user has no integer identifier.');
        }

        return OrganisationResource::collection($listOrganisations->handle($userId));
    }
}
