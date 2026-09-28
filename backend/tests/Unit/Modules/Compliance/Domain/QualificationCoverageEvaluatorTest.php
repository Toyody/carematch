<?php

namespace Tests\Unit\Modules\Compliance\Domain;

use App\Modules\Compliance\Domain\CredentialEvidence;
use App\Modules\Compliance\Domain\QualificationCoverageEvaluator;
use App\Modules\Compliance\Domain\QualificationCoverageStatus;
use App\Modules\Compliance\Domain\QualificationExpiryClassifier;
use App\Modules\Compliance\Domain\QualificationRequirement;
use App\Modules\Compliance\Domain\QualificationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QualificationCoverageEvaluatorTest extends TestCase
{
    #[DataProvider('expiryCases')]
    public function test_expiry_boundaries(?string $expiry, QualificationStatus $expected): void
    {
        $result = $this->evaluator()->evaluate(
            [new QualificationRequirement(10, 'First Aid')],
            [new CredentialEvidence(20, 10, $expiry === null ? null : new DateTimeImmutable($expiry))],
            new DateTimeImmutable('2026-09-28'),
            30,
        );

        self::assertSame($expected, $result->requirements[0]->status);
    }

    /** @return iterable<string, array{?string, QualificationStatus}> */
    public static function expiryCases(): iterable
    {
        yield 'yesterday' => ['2026-09-27', QualificationStatus::Expired];
        yield 'today' => ['2026-09-28', QualificationStatus::Expiring];
        yield 'warning boundary' => ['2026-10-28', QualificationStatus::Expiring];
        yield 'after warning boundary' => ['2026-10-29', QualificationStatus::Valid];
        yield 'non-expiring' => [null, QualificationStatus::Valid];
    }

    public function test_best_evidence_is_chosen_deterministically_and_missing_is_reported(): void
    {
        $result = $this->evaluator()->evaluate(
            [new QualificationRequirement(10, 'First Aid'), new QualificationRequirement(11, 'CPR')],
            [
                new CredentialEvidence(1, 10, new DateTimeImmutable('2025-01-01')),
                new CredentialEvidence(2, 10, new DateTimeImmutable('2027-01-01')),
            ],
            new DateTimeImmutable('2026-09-28'),
            30,
        );

        self::assertSame(QualificationStatus::Valid, $result->requirements[0]->status);
        self::assertSame(2, $result->requirements[0]->candidateQualificationId);
        self::assertSame(QualificationStatus::Missing, $result->requirements[1]->status);
        self::assertSame(QualificationCoverageStatus::NotSatisfied, $result->status);
    }

    public function test_expiring_only_yields_attention_and_no_requirements_is_satisfied(): void
    {
        $attention = $this->evaluator()->evaluate(
            [new QualificationRequirement(10, 'First Aid')],
            [new CredentialEvidence(1, 10, new DateTimeImmutable('2026-10-01'))],
            new DateTimeImmutable('2026-09-28'),
            30,
        );

        self::assertSame(QualificationCoverageStatus::AttentionRequired, $attention->status);
        self::assertSame(QualificationCoverageStatus::Satisfied, $this->evaluator()->evaluate([], [], new DateTimeImmutable('2026-09-28'), 30)->status);
    }

    public function test_equal_status_and_expiry_use_the_latest_id_as_a_stable_tie_break(): void
    {
        $result = $this->evaluator()->evaluate(
            [new QualificationRequirement(10, 'First Aid')],
            [
                new CredentialEvidence(20, 10, new DateTimeImmutable('2027-01-01')),
                new CredentialEvidence(21, 10, new DateTimeImmutable('2027-01-01')),
            ],
            new DateTimeImmutable('2026-09-28'),
            30,
        );

        self::assertSame(21, $result->requirements[0]->candidateQualificationId);
    }

    private function evaluator(): QualificationCoverageEvaluator
    {
        return new QualificationCoverageEvaluator(new QualificationExpiryClassifier);
    }
}
