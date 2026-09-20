<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class OrganisationMembership extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organisation_id',
        'user_id',
        'role',
        'deactivated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
