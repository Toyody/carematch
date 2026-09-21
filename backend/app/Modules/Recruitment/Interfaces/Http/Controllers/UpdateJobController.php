<?php

namespace App\Modules\Recruitment\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Application\Actions\UpdateJob;
use App\Modules\Recruitment\Application\Exceptions\InvalidJobSchedule;
use App\Modules\Recruitment\Application\Exceptions\JobNotFound;
use App\Modules\Recruitment\Interfaces\Http\Requests\UpdateJobRequest;
use App\Modules\Recruitment\Interfaces\Http\Resources\JobResource;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateJobController extends Controller
{
    public function __invoke(UpdateJobRequest $request, UpdateJob $updateJob): JobResource
    {
        try {
            return new JobResource($updateJob->handle(
                $request->tenantContext(),
                $request->jobId(),
                $request->jobChanges(),
            ));
        } catch (JobNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (InvalidJobSchedule) {
            throw ValidationException::withMessages([
                'closes_at' => ['The closing date must be after or equal to the opening date.'],
            ]);
        }
    }
}
