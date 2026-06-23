<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Wallet\Actions\CreditWallet;
use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminWithdrawalController extends Controller
{
    public function __construct(private readonly MpesaService $mpesaService) {}

    public function index(Request $request): JsonResponse
    {
        $withdrawals = MpesaTransaction::query()
            ->where('type', 'b2c')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->with('user')
            ->latest()
            ->paginate(25);

        return response()->json([
            'withdrawals' => $withdrawals->through(fn ($w) => [
                'id'                  => $w->id,
                'user'                => $w->user->name,
                'phone'               => $w->phone,
                'amount'              => number_format($w->amount, 2),
                'status'              => $w->status,
                'merchant_request_id' => $w->merchant_request_id,
                'created_at'          => $w->created_at->toDateTimeString(),
            ]),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page'    => $withdrawals->lastPage(),
                'total'        => $withdrawals->total(),
            ],
        ]);
    }

    // Re-fires a pending/failed B2C request with Safaricom.
    // Does NOT touch the wallet — the debit already happened
    // up front in InitiateB2cPayout.
    public function approve(MpesaTransaction $withdrawal): JsonResponse
    {
        if ($withdrawal->type !== 'b2c') {
            return response()->json(['message' => 'Not a withdrawal.'], 422);
        }

        if ($withdrawal->status === 'success') {
            return response()->json(['message' => 'Already paid out.'], 422);
        }

        try {
            $response = $this->mpesaService->b2cPayout(
                phone:     $withdrawal->phone,
                amount:    $withdrawal->amount,
                reference: 'LUDO_WD_RETRY_' . $withdrawal->id,
            );
        } catch (\Exception $e) {
            Log::error('Manual B2C retry failed', [
                'withdrawal_id' => $withdrawal->id,
                'error'         => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to contact M-Pesa: ' . $e->getMessage()], 502);
        }

        $withdrawal->update([
            'status'              => 'pending',
            'merchant_request_id' => $response['ConversationID'] ?? $withdrawal->merchant_request_id,
        ]);

        return response()->json(['message' => 'Payout re-queued with M-Pesa.']);
    }

    // Rejects a pending withdrawal and refunds the wallet, since the
    // debit already happened up front in InitiateB2cPayout.
    public function reject(MpesaTransaction $withdrawal): JsonResponse
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => 'Only pending withdrawals can be rejected.'], 422);
        }

        DB::transaction(function () use ($withdrawal) {
            $withdrawal->update(['status' => 'failed']);

            app(CreditWallet::class)->execute(new TransactionDTO(
                wallet:      $withdrawal->user->wallet,
                type:        'refund',
                amount:      $withdrawal->amount,
                reference:   'withdrawal_reject_' . $withdrawal->id . '_' . uniqid(),
                description: "Withdrawal #{$withdrawal->id} rejected by admin",
            ));
        });

        return response()->json(['message' => 'Withdrawal rejected and wallet refunded.']);
    }
}