<?php

namespace App\Modules\Organisation\Application\Data;

enum OrganisationRole: string
{
    case Admin = 'admin';
    case Recruiter = 'recruiter';
    case HiringManager = 'hiring_manager';
}
