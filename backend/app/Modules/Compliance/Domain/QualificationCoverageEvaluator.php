<?php

namespace App\Modules\Compliance\Domain;

use DateTimeImmutable;

final class QualificationCoverageEvaluator
{
    public function __construct(private readonly QualificationExpiryClassifier $expiryClassifier) {}

    /**
     * @param  list<QualificationRequirement>  $requirements
     * @param  list<CredentialEvidence>  $credentials
     */
    public function evaluate(array $requirements, array $credentials, DateTimeImmutable $today, int $warningDays): QualificationCoverage
    {
        $byDefinition = [];
        foreach ($credentials as $credential) {
            $byDefinition[$credential->qualificationDefinitionId][] = $credential;
        }

        $results = [];
        foreach ($requirements as $requirement) {
            $results[] = $this->evaluateRequirement(
                $requirement,
                $byDefinition[$requirement->qualificationDefinitionId] ?? [],
                $today,
                $warningDays,
            );
        }

        $statuses = array_map(static fn (QualificationRequirementResult $result): QualificationStatus => $result->status, $results);
        $overall = in_array(QualificationStatus::Missing, $statuses, true)
            || in_array(QualificationStatus::Expired, $statuses, true)
            ? QualificationCoverageStatus::NotSatisfied
            : (in_array(QualificationStatus::Expiring, $statuses, true)
                ? QualificationCoverageStatus::AttentionRequired
                : QualificationCoverageStatus::Satisfied);

        return new QualificationCoverage($overall, $results);
    }

    /** @param list<CredentialEvidence> $credentials */
    private function evaluateRequirement(
        QualificationRequirement $requirement,
        array $credentials,
        DateTimeImmutable $today,
        int $warningDays,
    ): QualificationRequirementResult {
        if ($credentials === []) {
            return new QualificationRequirementResult(
                $requirement->qualificationDefinitionId,
                $requirement->name,
                QualificationStatus::Missing,
                null,
                null,
            );
        }

        usort($credentials, function (CredentialEvidence $left, CredentialEvidence $right) use ($today, $warningDays): int {
            $rank = fn (CredentialEvidence $credential): int => match ($this->expiryClassifier->classify($credential->expiresOn, $today, $warningDays)) {
                QualificationStatus::Valid => 3,
                QualificationStatus::Expiring => 2,
                QualificationStatus::Expired => 1,
                QualificationStatus::Missing => 0,
            };
            $rankComparison = $rank($right) <=> $rank($left);
            if ($rankComparison !== 0) {
                return $rankComparison;
            }

            $expiryComparison = ($right->expiresOn?->getTimestamp() ?? PHP_INT_MAX)
                <=> ($left->expiresOn?->getTimestamp() ?? PHP_INT_MAX);
            if ($expiryComparison !== 0) {
                return $expiryComparison;
            }

            return $right->id <=> $left->id;
        });

        $best = $credentials[0];

        return new QualificationRequirementResult(
            $requirement->qualificationDefinitionId,
            $requirement->name,
            $this->expiryClassifier->classify($best->expiresOn, $today, $warningDays),
            $best->id,
            $best->expiresOn,
        );
    }
}
