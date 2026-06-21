<?php

namespace App\Domain\Mpesa\Actions;

use App\Domain\Mpesa\DTOs\StkPushDTO;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;

class InitiateStkPush
{
    public function __construct(private readonly MpesaService $mpesaService) {}

    public function execute(StkPushDTO $dto): MpesaTransaction
    {
        $reference = 'LUDO_DEP_' . strtoupper(uniqid());

        $response = $this->mpesaService->stkPush(
            phone:       $dto->phone,
            amount:      $dto->amount,
            reference:   $reference,
            description: 'Ludo wallet deposit',
        );

        return MpesaTransaction::create([
            'user_id'              => $dto->userId,
            'type'                 => 'stk_push',
            'merchant_request_id'  => $response['MerchantRequestID'],
            'checkout_request_id'  => $response['CheckoutRequestID'],
            'amount'               => $dto->amount,
            'phone'                => $dto->phone,
            'status'               => 'pending',
        ]);
    }
}