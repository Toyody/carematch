<?php

namespace App\Modules\Compliance\Domain;

enum QualificationStatus: string
{
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Missing = 'missing';
}
