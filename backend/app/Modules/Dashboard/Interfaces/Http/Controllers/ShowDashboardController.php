<?php

namespace App\Modules\Dashboard\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Application\Actions\ViewDashboard;
use App\Modules\Dashboard\Interfaces\Http\Resources\DashboardResource;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Http\Request;
use LogicException;

final class ShowDashboardController extends Controller
{
    public function __invoke(Request $request, ViewDashboard $viewDashboard): DashboardResource
    {
        $tenant = $request->attributes->get(TenantContext::class);

        if (! $tenant instanceof TenantContext) {
            throw new LogicException('The tenant context has not been resolved.');
        }

        return new DashboardResource($viewDashboard->handle($tenant));
    }
}
