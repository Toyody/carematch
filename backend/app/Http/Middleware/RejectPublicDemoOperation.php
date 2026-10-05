<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RejectPublicDemoOperation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $operation): Response
    {
        if (config('carematch.portfolio_demo.public_mode') !== true) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => sprintf('%s is unavailable in the public portfolio demo.', $this->label($operation)),
        ], Response::HTTP_FORBIDDEN);
    }

    private function label(string $operation): string
    {
        return match ($operation) {
            'ai-assistance' => 'AI assistance',
            'candidate-document-write' => 'Candidate document changes',
            'expiry-digest' => 'Credential expiry digest delivery',
            'invitation-creation' => 'Invitation sending',
            'organisation-creation' => 'Organisation creation',
            'password-recovery' => 'Password recovery',
            'registration' => 'Registration',
            default => 'This operation',
        };
    }
}
