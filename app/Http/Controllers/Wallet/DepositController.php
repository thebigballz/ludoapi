<?php

namespace App\Http\Controllers\Wallet;

use App\Domain\Mpesa\Actions\InitiateStkPush;
use App\Domain\Mpesa\DTOs\StkPushDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\DepositRequest;
use Illuminate\Http\JsonResponse;

class DepositController extends Controller
{
    public function __construct(private readonly InitiateStkPush $initiateStkPush) {}

    public function __invoke(DepositRequest $request): JsonResponse
    {
        try {
            $mpesaTx = $this->initiateStkPush->execute(
                StkPushDTO::fromRequest($request->validated(), $request->user()->id)
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message'             => 'STK push sent. Enter your MPESA PIN to complete.',
            'checkout_request_id' => $mpesaTx->checkout_request_id,
        ]);
    }
}