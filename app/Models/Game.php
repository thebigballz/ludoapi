<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        'firebase_room_id',
        'status',
        'stake_amount',
        'platform_fee',
        'winner_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'stake_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'started_at'   => 'datetime',
            'ended_at'     => 'datetime',
        ];
    }

    public function players()
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function escrows()
    {
        return $this->hasMany(GameEscrow::class);
    }

    public function isFull(): bool
    {
        return $this->players()->count() >= config('ludo.players_per_game');
    }

    public function hasPlayer(int $userId): bool
    {
        return $this->players()->where('user_id', $userId)->exists();
    }
}