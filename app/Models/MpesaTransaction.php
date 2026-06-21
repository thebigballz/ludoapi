<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'merchant_request_id',
        'checkout_request_id',
        'mpesa_receipt',
        'amount',
        'phone',
        'status',
        'raw_callback',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'raw_callback' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransactions()
    {
        return $this->morphMany(WalletTransaction::class, 'transactionable');
    }
}