<?php

namespace Tests\Unit\Modules\Recruitment\Domain;

use App\Modules\Recruitment\Domain\JobStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobStatusTest extends TestCase
{
    #[DataProvider('transitionMatrix')]
    public function test_only_defined_job_lifecycle_transitions_are_allowed(
        JobStatus $current,
        JobStatus $target,
        bool $allowed,
    ): void {
        self::assertSame($allowed, $current->canTransitionTo($target));
    }

    /** @return array<string, array{JobStatus, JobStatus, bool}> */
    public static function transitionMatrix(): array
    {
        $allowed = [
            'draft:open', 'draft:archived',
            'open:closed', 'open:archived',
            'closed:open', 'closed:archived',
        ];
        $cases = [];
        foreach (JobStatus::cases() as $current) {
            foreach (JobStatus::cases() as $target) {
                $key = "{$current->value}:{$target->value}";
                $cases[$key] = [$current, $target, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }
}
