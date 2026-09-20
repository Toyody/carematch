<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class OrganisationInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organisation_id',
        'email',
        'role',
        'token_hash',
        'invited_by_user_id',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
