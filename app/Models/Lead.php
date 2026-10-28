<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'company',
        'email',
        'budget_band',
        'authority',
        'need_summary',
        'timeline',
        'score',
        'grade',
        'status',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'score' => 'integer',
        ];
    }

    /** @return HasMany<ChatTurn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(ChatTurn::class);
    }
}
