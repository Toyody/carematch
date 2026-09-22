<?php

namespace Tests\Unit\Modules\Recruitment\Domain;

use App\Modules\Recruitment\Domain\ApplicationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationStatusTest extends TestCase
{
    #[DataProvider('statusPairs')]
    public function test_transition_graph_is_exact(string $from, string $to, bool $allowed): void
    {
        self::assertSame(
            $allowed,
            ApplicationStatus::from($from)->canTransitionTo(ApplicationStatus::from($to)),
        );
    }

    /** @return array<string, array{string, string, bool}> */
    public static function statusPairs(): array
    {
        $allowed = [
            'applied:screening', 'applied:rejected',
            'screening:interview', 'screening:rejected',
            'interview:offer', 'interview:rejected',
            'offer:hired', 'offer:rejected',
        ];
        $cases = [];

        foreach (ApplicationStatus::cases() as $from) {
            foreach (ApplicationStatus::cases() as $to) {
                $key = "{$from->value}:{$to->value}";
                $cases[$key] = [$from->value, $to->value, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }
}
