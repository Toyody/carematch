<?php

namespace Tests\Feature\Infrastructure;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\ApplicationStatusHistory;
use App\Modules\Recruitment\Infrastructure\Persistence\RecruitmentApplication;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class PortfolioDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_run_in_production(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('carematch.portfolio_demo.enabled', true);
        config()->set('carematch.portfolio_demo.password', 'synthetic-demo-password');

        try {
            $this->runDemoSeeder();
            self::fail('The portfolio demo seeder should reject production.');
        } catch (RuntimeException $exception) {
            self::assertSame('Portfolio demo seeding is disabled in production.', $exception->getMessage());
        } finally {
            $this->app->detectEnvironment(static fn (): string => $originalEnvironment);
        }
    }

    public function test_it_requires_explicit_opt_in_and_password(): void
    {
        config()->set('carematch.portfolio_demo.enabled', false);

        try {
            $this->runDemoSeeder();
            self::fail('The portfolio demo seeder should require explicit opt-in.');
        } catch (RuntimeException $exception) {
            self::assertSame('Set CARE_MATCH_DEMO_DATA=true to seed portfolio demo data.', $exception->getMessage());
        }

        config()->set('carematch.portfolio_demo.enabled', true);
        config()->set('carematch.portfolio_demo.password', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CARE_MATCH_DEMO_PASSWORD is required.');
        $this->runDemoSeeder();
    }

    public function test_it_creates_repeatable_synthetic_tenant_data_with_coherent_history(): void
    {
        $this->enableDemo();

        $this->runDemoSeeder();
        $firstCounts = $this->demoCounts();
        $this->runDemoSeeder();

        self::assertSame($firstCounts, $this->demoCounts());
        self::assertSame([
            'applications' => 6,
            'audit_events' => 5,
            'candidates' => 6,
            'documents' => 0,
            'history' => 18,
            'jobs' => 6,
            'memberships' => 1,
            'organisations' => 1,
            'users' => 1,
        ], $firstCounts);

        $user = User::query()->sole();
        self::assertSame('demo.admin@example.test', $user->getAttribute('email'));
        self::assertTrue(Hash::check('synthetic-demo-password', (string) $user->getAuthPassword()));

        $candidateEmails = DB::table('candidates')->pluck('email')->all();
        foreach ($candidateEmails as $email) {
            self::assertIsString($email);
            self::assertStringEndsWith('@example.test', $email);
        }

        $statusCounts = RecruitmentApplication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
        ksort($statusCounts);
        self::assertSame([
            'applied' => 1,
            'hired' => 1,
            'interview' => 1,
            'offer' => 1,
            'rejected' => 1,
            'screening' => 1,
        ], $statusCounts);

        foreach (RecruitmentApplication::query()->get() as $application) {
            $current = $application->getAttribute('status');
            self::assertInstanceOf(ApplicationStatus::class, $current);

            $history = ApplicationStatusHistory::query()
                ->where('organisation_id', $application->getAttribute('organisation_id'))
                ->where('application_id', $application->getKey())
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
            self::assertNotEmpty($history);
            self::assertNull($history->first()?->getAttribute('from_status'));
            self::assertSame(ApplicationStatus::Applied, $history->first()?->getAttribute('to_status'));

            $previous = ApplicationStatus::Applied;
            foreach ($history->skip(1) as $event) {
                $from = $event->getAttribute('from_status');
                $to = $event->getAttribute('to_status');
                self::assertSame($previous, $from);
                self::assertInstanceOf(ApplicationStatus::class, $to);
                self::assertTrue($previous->canTransitionTo($to));
                $previous = $to;
            }

            self::assertSame($current, $previous);
        }
    }

    private function enableDemo(): void
    {
        config()->set('carematch.portfolio_demo.enabled', true);
        config()->set('carematch.portfolio_demo.email', 'demo.admin@example.test');
        config()->set('carematch.portfolio_demo.password', 'synthetic-demo-password');
    }

    private function runDemoSeeder(): void
    {
        $seeder = new PortfolioDemoSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();
    }

    /** @return array<string, int> */
    private function demoCounts(): array
    {
        return [
            'applications' => DB::table('applications')->count(),
            'audit_events' => DB::table('audit_events')->count(),
            'candidates' => DB::table('candidates')->count(),
            'documents' => DB::table('candidate_documents')->count(),
            'history' => DB::table('application_status_history')->count(),
            'jobs' => DB::table('jobs')->count(),
            'memberships' => DB::table('organisation_memberships')->count(),
            'organisations' => DB::table('organisations')->count(),
            'users' => DB::table('users')->count(),
        ];
    }
}
