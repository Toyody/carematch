<?php

namespace App\Modules\Organisation\Interfaces\Http\Middleware;

use App\Modules\Organisation\Application\Contracts\ActiveTenantMembershipResolver;
use App\Modules\Organisation\Application\Data\TenantContext;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ResolveTenantContext
{
    public function __construct(
        private ActiveTenantMembershipResolver $memberships,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_int($userId)) {
            throw new LogicException('The authenticated user has no integer identifier.');
        }

        $organisationId = $this->routeOrganisationId($request);

        if ($organisationId === null) {
            throw new NotFoundHttpException('Not Found');
        }

        $tenant = $this->memberships->resolve($userId, $organisationId);

        if ($tenant === null) {
            throw new NotFoundHttpException('Not Found');
        }

        $request->attributes->set(TenantContext::class, $tenant);

        return $next($request);
    }

    private function routeOrganisationId(Request $request): ?int
    {
        $value = $request->route('organisation');

        if (! is_string($value)) {
            return null;
        }

        $organisationId = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($organisationId) ? $organisationId : null;
    }
}
