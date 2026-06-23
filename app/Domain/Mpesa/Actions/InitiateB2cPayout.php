<?php

namespace App\Domain\Mpesa\Actions;

use App\Domain\Mpesa\DTOs\B2cPayoutDTO;
use App\Domain\Wallet\Actions\DebitWallet;
use App\Domain\Wallet\DTOs\TransactionDTO;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Jobs\FlagSuspiciousAccount;
use App\Models\MpesaTransaction;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitiateB2cPayout
{
    public function __construct(
        private readonly MpesaService $mpesaService,
        private readonly DebitWallet  $debitWallet,
    ) {}

    /**
     * @throws InsufficientBalanceException
     */
    public function execute(B2cPayoutDTO $dto): MpesaTransaction
    {
        $user = User::findOrFail($dto->userId);

        return DB::transaction(function () use ($dto, $user) {
            $this->debitWallet->execute(new TransactionDTO(
                wallet:      $user->wallet,
                type:        'withdrawal',
                amount:      $dto->amount,
                reference:   'withdrawal_' . Str::uuid(),
                description: 'MPESA withdrawal to ' . $dto->phone,
            ));

            $response = $this->mpesaService->b2cPayout(
                phone:     $dto->phone,
                amount:    $dto->amount,
                reference: 'LUDO_WD_' . strtoupper(uniqid()),
            );

            $mpesaTx = MpesaTransaction::create([
                'user_id'             => $dto->userId,
                'type'                => 'b2c',
                'merchant_request_id' => $response['ConversationID'],
                'amount'              => $dto->amount,
                'phone'               => $dto->phone,
                'status'              => 'pending',
            ]);

            FlagSuspiciousAccount::dispatch($dto->userId); // <-- added

            return $mpesaTx;
        });
    }
}