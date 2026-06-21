<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function balance(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;

        return response()->json([
            'wallet' => new WalletResource($wallet),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->wallet
            ->transactions()
            ->latest()
            ->paginate(20);

        return response()->json([
            'transactions' => TransactionResource::collection($transactions),
            'meta'         => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'total'        => $transactions->total(),
            ],
        ]);
    }
}