<?php

namespace App\Domain\Mpesa\Actions;

use App\Domain\Mpesa\DTOs\StkCallbackDTO;
use App\Domain\Wallet\Actions\CreditWallet;
use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\Log;

class HandleStkCallback
{
    public function __construct(private readonly CreditWallet $creditWallet) {}

    public function execute(StkCallbackDTO $dto): void
    {
        $mpesaTx = MpesaTransaction::where('merchant_request_id', $dto->merchantRequestId)
            ->first();

        if (! $mpesaTx) {
            Log::warning('STK callback for unknown transaction', ['dto' => $dto]);
            return;
        }

        // Idempotency — ignore if already processed
        if ($mpesaTx->status !== 'pending') {
            return;
        }

        $mpesaTx->update([
            'status'       => $dto->resultCode === 0 ? 'success' : 'failed',
            'mpesa_receipt'=> $dto->mpesaReceiptNumber,
            'raw_callback' => $dto->rawPayload,
        ]);

        // Only credit wallet on success
        if ($dto->resultCode !== 0) {
            Log::info('STK push failed by user', ['desc' => $dto->resultDesc]);
            return;
        }

        $wallet = $mpesaTx->user->wallet;

        $this->creditWallet->execute(new TransactionDTO(
            wallet:          $wallet,
            type:            'deposit',
            amount:          $dto->amount,
            reference:       'mpesa_' . $dto->mpesaReceiptNumber,
            description:     'MPESA deposit — ' . $dto->mpesaReceiptNumber,
            transactionable: $mpesaTx,
        ));
    }
}