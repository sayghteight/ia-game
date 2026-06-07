<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = [
        'game_id', 'name', 'character_name', 'character_class',
        'hp', 'hp_max', 'gold', 'level', 'xp', 'inventory',
        'session_token', 'is_creator',
    ];

    protected $hidden = ['session_token'];

    protected $casts = [
        'inventory' => 'array',
        'hp' => 'integer',
        'hp_max' => 'integer',
        'gold' => 'integer',
        'level' => 'integer',
        'xp' => 'integer',
        'is_creator' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getInventoryListAttribute(): array
    {
        return is_array($this->inventory) ? $this->inventory : [];
    }
}
