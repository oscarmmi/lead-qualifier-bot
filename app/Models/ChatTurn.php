<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatTurn extends Model
{
    protected $fillable = [
        'role',
        'content',
        'extracted_json',
    ];

    protected function casts(): array
    {
        return [
            'extracted_json' => 'array',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
