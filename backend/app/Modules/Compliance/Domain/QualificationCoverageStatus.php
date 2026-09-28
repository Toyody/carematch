<?php

namespace App\Modules\Compliance\Domain;

enum QualificationCoverageStatus: string
{
    case Satisfied = 'satisfied';
    case AttentionRequired = 'attention_required';
    case NotSatisfied = 'not_satisfied';
}
