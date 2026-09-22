<?php

namespace App\Modules\Candidate\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class CandidateDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organisation_id',
        'candidate_id',
        'original_name',
        'storage_key',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'storage_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
