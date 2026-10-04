<?php

namespace App\Modules\Matching\Domain;

enum OccupationMatchStatus: string
{
    case Match = 'match';
    case Unknown = 'unknown';
    case Mismatch = 'mismatch';
}
