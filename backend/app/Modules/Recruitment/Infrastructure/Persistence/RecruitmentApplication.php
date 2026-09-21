<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Domain\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;

final class RecruitmentApplication extends Model
{
    protected $table = 'applications';

    /** @var list<string> */
    protected $fillable = [
        'organisation_id',
        'job_id',
        'candidate_id',
        'status',
        'applied_at',
        'created_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'immutable_datetime',
        ];
    }
}
