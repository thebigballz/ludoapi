<?php

namespace App\Domain\Mpesa\Actions;

use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\Log;

class HandleB2cResult
{
    public function execute(array $payload): void
    {
        $result = $payload['Result'] ?? [];

        $conversationId = $result['ConversationID'] ?? null;
        $resultCode     = $result['ResultCode'] ?? -1;
        $receipt        = $result['ResultParameters']['ResultParameter'] ?? [];

        $receiptNumber = collect($receipt)
            ->firstWhere('Key', 'TransactionReceipt')['Value'] ?? null;

        $mpesaTx = MpesaTransaction::where('merchant_request_id', $conversationId)->first();

        if (! $mpesaTx) {
            Log::warning('B2C result for unknown transaction', ['conversation_id' => $conversationId]);
            return;
        }

        if ($mpesaTx->status !== 'pending') {
            return;
        }

        $mpesaTx->update([
            'status'        => $resultCode === 0 ? 'success' : 'failed',
            'mpesa_receipt' => $receiptNumber,
            'raw_callback'  => $payload,
        ]);

        if ($resultCode !== 0) {
            Log::error('B2C payout failed', ['result' => $result]);
            // TODO: reverse the wallet debit if B2C fails
        }
    }
}