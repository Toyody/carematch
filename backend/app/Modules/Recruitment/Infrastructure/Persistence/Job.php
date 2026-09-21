<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Domain\JobStatus;
use Illuminate\Database\Eloquent\Model;

final class Job extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'organisation_id',
        'title',
        'occupation',
        'location',
        'employment_type',
        'description',
        'status',
        'opened_at',
        'closes_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'opened_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
        ];
    }
}
