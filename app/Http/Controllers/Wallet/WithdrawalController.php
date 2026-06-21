<?php

namespace App\Http\Controllers\Wallet;

use App\Domain\Mpesa\Actions\InitiateB2cPayout;
use App\Domain\Mpesa\DTOs\B2cPayoutDTO;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\WithdrawalRequest;
use Illuminate\Http\JsonResponse;

class WithdrawalController extends Controller
{
    public function __construct(private readonly InitiateB2cPayout $initiateB2cPayout) {}

    public function __invoke(WithdrawalRequest $request): JsonResponse
    {
        try {
            $this->initiateB2cPayout->execute(
                B2cPayoutDTO::fromRequest($request->validated(), $request->user()->id)
            );
        } catch (InsufficientBalanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'message' => 'Withdrawal initiated. Funds will arrive shortly.',
        ]);
    }
}