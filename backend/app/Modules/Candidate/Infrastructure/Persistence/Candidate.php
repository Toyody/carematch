<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class Candidate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organisation_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'occupation',
        'location',
        'availability',
        'notes',
    ];
}
