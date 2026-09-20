<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class Organisation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];
}
