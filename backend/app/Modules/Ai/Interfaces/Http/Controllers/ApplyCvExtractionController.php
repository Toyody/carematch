<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\Actions\ApplyCvExtraction;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Application\Exceptions\AiReviewUnavailable;
use App\Modules\Ai\Application\Exceptions\StaleAiReview;
use App\Modules\Ai\Interfaces\Http\Requests\ApplyCvExtractionRequest;
use App\Modules\Candidate\Interfaces\Http\Resources\CandidateResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApplyCvExtractionController extends Controller
{
    public function __invoke(ApplyCvExtractionRequest $request, ApplyCvExtraction $action): CandidateResource|JsonResponse
    {
        try {
            $candidate = $action->handle(
                $request->tenantContext(), $request->routeId('candidate'), $request->routeId('document'),
                $request->routeId('extraction'), $request->changes(),
            );
        } catch (AiResourceNotFound) {
            throw new NotFoundHttpException('Not Found');
        } catch (StaleAiReview) {
            return new JsonResponse(['message' => 'The candidate changed after this AI draft was generated. Refresh and review again.', 'code' => 'stale_candidate'], Response::HTTP_CONFLICT);
        } catch (AiReviewUnavailable) {
            return new JsonResponse(['message' => 'This AI extraction is not available for review.'], Response::HTTP_CONFLICT);
        }

        return new CandidateResource($candidate);
    }
}
