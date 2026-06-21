<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'total_deposited',
        'total_withdrawn',
        'total_won',
        'total_lost',
    ];

    protected function casts(): array
    {
        return [
            'balance'         => 'decimal:2',
            'total_deposited' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'total_won'       => 'decimal:2',
            'total_lost'      => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}