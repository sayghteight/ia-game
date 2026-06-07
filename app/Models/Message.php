<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'game_id',
        'role',
        'content',
        'tokens_input',
        'tokens_output',
    ];

    protected $casts = [
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
