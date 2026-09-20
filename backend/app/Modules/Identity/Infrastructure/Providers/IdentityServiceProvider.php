<?php

namespace App\Modules\Identity\Infrastructure\Providers;

use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Identity\Application\Contracts\PasswordResetStore;
use App\Modules\Identity\Application\Contracts\UserRegistrationStore;
use App\Modules\Identity\Application\Support\EmailNormalizer;
use App\Modules\Identity\Infrastructure\Persistence\EloquentIdentityUserLookup;
use App\Modules\Identity\Infrastructure\Persistence\EloquentPasswordResetStore;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRegistrationStore;
use App\Modules\Identity\Interfaces\Validation\Rules\BcryptCompatiblePassword;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CanonicalEmailNormalizer::class, EmailNormalizer::class);
        $this->app->bind(IdentityUserLookup::class, EloquentIdentityUserLookup::class);

        $this->app->bind(
            UserRegistrationStore::class,
            EloquentUserRegistrationStore::class,
        );

        $this->app->bind(
            PasswordResetStore::class,
            EloquentPasswordResetStore::class,
        );
    }

    public function boot(EmailNormalizer $emailNormalizer): void
    {
        Password::defaults(
            static fn (): Password => Password::min(12)
                ->rules([new BcryptCompatiblePassword]),
        );

        RateLimiter::for(
            'identity-registration',
            static fn (Request $request): Limit => Limit::perMinute(3)
                ->by('identity:register:'.($request->ip() ?? 'unknown'))
                ->response(
                    static fn (Request $_request, array $headers): JsonResponse => response()->json([
                        'message' => 'Too many attempts. Please try again later.',
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers),
                ),
        );

        RateLimiter::for(
            'identity-forgot-password',
            fn (Request $request): Limit => Limit::perMinute(3)
                ->by($this->passwordRateLimitKey(
                    'identity:forgot-password:',
                    $request,
                    $emailNormalizer,
                ))
                ->response(
                    static fn (Request $_request, array $headers): JsonResponse => self::rateLimitedResponse($headers),
                ),
        );

        RateLimiter::for(
            'identity-reset-password',
            fn (Request $request): Limit => Limit::perMinute(5)
                ->by($this->passwordRateLimitKey(
                    'identity:reset-password:',
                    $request,
                    $emailNormalizer,
                ))
                ->response(
                    static fn (Request $_request, array $headers): JsonResponse => self::rateLimitedResponse($headers),
                ),
        );

        ResetPasswordNotification::createUrlUsing(
            static function (CanResetPassword $user, string $token): string {
                $frontendUrl = config('carematch.frontend_url');

                if (! is_string($frontendUrl) || $frontendUrl === '') {
                    throw new LogicException('The CareMatch frontend URL is not configured.');
                }

                $query = http_build_query([
                    'token' => $token,
                    'email' => (string) $user->getEmailForPasswordReset(),
                ], '', '&', PHP_QUERY_RFC3986);

                return rtrim($frontendUrl, '/').'/reset-password?'.$query;
            },
        );
    }

    private function passwordRateLimitKey(
        string $prefix,
        Request $request,
        EmailNormalizer $emailNormalizer,
    ): string {
        $rawEmail = $request->input('email');
        $email = is_string($rawEmail)
            ? $emailNormalizer->normalize($rawEmail)
            : '__carematch_invalid_email_input__';
        $identity = $email."\0".($request->ip() ?? 'unknown');

        return $prefix.hash('sha256', $identity);
    }

    /**
     * @param  array<string, int>  $headers
     */
    private static function rateLimitedResponse(array $headers): JsonResponse
    {
        return response()->json([
            'message' => 'Too many attempts. Please try again later.',
        ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
    }
}
