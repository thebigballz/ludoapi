<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameEscrow extends Model
{
    protected $fillable = [
        'wallet_id',
        'game_id',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function transactions()
    {
        return $this->morphMany(WalletTransaction::class, 'transactionable');
    }
}