<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class QualificationDefinition extends Model
{
    /** @var list<string> */
    protected $fillable = ['organisation_id', 'name', 'category', 'description', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
