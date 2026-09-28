<?php

namespace Tests\Feature\Infrastructure;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProvisionPublicDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_production_public_demo_mode_and_explicit_confirmation(): void
    {
        config()->set('carematch.portfolio_demo.public_mode', true);

        $this->artisan('carematch:demo:provision', [
            '--confirm' => 'PROVISION_SYNTHETIC_PUBLIC_DEMO',
        ])->assertFailed();

        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(static fn (): string => 'production');

        try {
            config()->set('carematch.portfolio_demo.public_mode', false);
            $this->artisan('carematch:demo:provision', [
                '--confirm' => 'PROVISION_SYNTHETIC_PUBLIC_DEMO',
            ])->assertFailed();

            config()->set('carematch.portfolio_demo.public_mode', true);
            $this->artisan('carematch:demo:provision')->assertFailed();
        } finally {
            $this->app->detectEnvironment(static fn (): string => $originalEnvironment);
        }
    }

    public function test_command_provisions_only_the_guarded_synthetic_dataset(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('carematch.portfolio_demo.public_mode', true);
        config()->set('carematch.portfolio_demo.email', 'public.demo@example.test');
        config()->set('carematch.portfolio_demo.password', 'synthetic-demo-password');

        try {
            $this->artisan('carematch:demo:provision', [
                '--confirm' => 'PROVISION_SYNTHETIC_PUBLIC_DEMO',
            ])->assertSuccessful();
        } finally {
            $this->app->detectEnvironment(static fn (): string => $originalEnvironment);
        }

        self::assertSame('public.demo@example.test', User::query()->sole()->getAttribute('email'));
        $this->assertDatabaseCount('organisations', 1);
        $this->assertDatabaseCount('candidates', 6);
        $this->assertDatabaseCount('jobs', 6);
        $this->assertDatabaseCount('applications', 6);
    }
}
