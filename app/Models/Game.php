<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    protected $fillable = [
        'code', 'pin', 'title', 'current_round',
        'location', 'world_notes',
    ];

    protected $casts = [
        'current_round' => 'integer',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('created_at');
    }

    public function creator(): HasOne
    {
        return $this->hasOne(Player::class)->where('is_creator', true);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    public function pendingMessages(): HasMany
    {
        return $this->hasMany(Message::class)
            ->where('status', 'pending')
            ->orderBy('created_at');
    }
}
