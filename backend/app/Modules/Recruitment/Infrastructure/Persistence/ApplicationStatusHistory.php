<?php

namespace App\Modules\Recruitment\Infrastructure\Persistence;

use App\Modules\Recruitment\Domain\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;

final class ApplicationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'application_status_history';

    /** @var list<string> */
    protected $fillable = [
        'organisation_id',
        'application_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
