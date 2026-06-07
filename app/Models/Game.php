<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'title',
        'character_name',
        'character_class',
        'hp',
        'hp_max',
        'gold',
        'level',
        'experience',
        'location',
        'inventory',
        'status_notes',
        'is_active',
    ];

    protected $casts = [
        'inventory' => 'array',
        'hp' => 'integer',
        'hp_max' => 'integer',
        'gold' => 'integer',
        'level' => 'integer',
        'experience' => 'integer',
        'is_active' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    public function getInventoryListAttribute(): array
    {
        return is_array($this->inventory) ? $this->inventory : [];
    }
}
