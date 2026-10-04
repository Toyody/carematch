<?php

namespace App\Modules\Matching\Domain;

enum QualificationMatchStatus: string
{
    case Satisfied = 'satisfied';
    case AttentionRequired = 'attention_required';
    case NotSatisfied = 'not_satisfied';
}
