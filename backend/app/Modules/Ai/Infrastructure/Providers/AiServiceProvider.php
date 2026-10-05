<?php

namespace App\Modules\Ai\Infrastructure\Providers;

use App\Modules\Ai\Application\Contracts\AiProvider;
use App\Modules\Ai\Application\Contracts\AiRequestDispatcher;
use App\Modules\Ai\Application\Contracts\AiReviewTransaction;
use App\Modules\Ai\Application\Contracts\CvExtractionStore;
use App\Modules\Ai\Application\Contracts\MatchExplanationStore;
use App\Modules\Ai\Infrastructure\Persistence\EloquentAiReviewTransaction;
use App\Modules\Ai\Infrastructure\Persistence\EloquentCvExtractionStore;
use App\Modules\Ai\Infrastructure\Persistence\EloquentMatchExplanationStore;
use App\Modules\Ai\Infrastructure\Queue\LaravelAiRequestDispatcher;
use App\Modules\Ai\Interfaces\Authorization\AiPolicy;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CvExtractionStore::class, EloquentCvExtractionStore::class);
        $this->app->bind(MatchExplanationStore::class, EloquentMatchExplanationStore::class);
        $this->app->bind(AiRequestDispatcher::class, LaravelAiRequestDispatcher::class);
        $this->app->bind(AiReviewTransaction::class, EloquentAiReviewTransaction::class);
        $this->app->bind(AiProvider::class, function (): AiProvider {
            return match (config('carematch.ai.provider')) {
                'fake' => new FakeAiProvider,
                'openai' => new OpenAiProvider,
                default => throw new LogicException('Unsupported AI provider configuration.'),
            };
        });
    }

    public function boot(AiPolicy $policy): void
    {
        Gate::define(AiPolicy::VIEW, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->view($user, $tenant));
        Gate::define(AiPolicy::USE, static fn (Authenticatable $user, TenantContext $tenant): bool => $policy->use($user, $tenant));

        RateLimiter::for('ai-cv-extraction', static function (Request $request): Limit {
            return self::limit($request, 'cv', 5);
        });
        RateLimiter::for('ai-match-explanation', static function (Request $request): Limit {
            return self::limit($request, 'match', 10);
        });
    }

    private static function limit(Request $request, string $operation, int $attempts): Limit
    {
        $tenant = $request->attributes->get(TenantContext::class);
        $identity = $tenant instanceof TenantContext
            ? sprintf('%d:%d', $tenant->organisationId, $tenant->userId)
            : 'unresolved:'.($request->ip() ?? 'unknown');

        return Limit::perMinute($attempts)->by(hash('sha256', "ai:{$operation}:{$identity}"));
    }
}
