<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class JobQualificationRequirement extends Model
{
    /** @var list<string> */
    protected $fillable = ['organisation_id', 'job_id', 'qualification_definition_id'];
}
