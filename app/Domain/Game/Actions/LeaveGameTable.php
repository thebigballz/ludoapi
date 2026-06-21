<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\RefundEscrow;
use App\Models\Game;
use App\Models\GameEscrow;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;

class LeaveGameTable
{
    public function __construct(
        private readonly RefundEscrow    $refundEscrow,
        private readonly FirebaseService $firebaseService,
    ) {}

    /**
     * @throws InvalidGameStateException
     */
    public function execute(Game $game, User $user): void
    {
        // Can only leave a waiting game
        if ($game->status !== 'waiting') {
            throw new InvalidGameStateException(
                'Cannot leave a game that has already started.'
            );
        }

        DB::transaction(function () use ($game, $user) {
            // Refund only this player's escrow
            $escrow = GameEscrow::where('game_id', $game->id)
                ->where('wallet_id', $user->wallet->id)
                ->where('status', 'held')
                ->first();

            if ($escrow) {
                $escrow->update(['status' => 'refunded']);
                app(\App\Domain\Wallet\Actions\CreditWallet::class)->execute(
                    new \App\Domain\Wallet\DTOs\TransactionDTO(
                        wallet:      $user->wallet,
                        type:        'refund',
                        amount:      $escrow->amount,
                        reference:   'leave_refund_' . $escrow->id . '_' . uniqid(),
                        description: "Refund for leaving game #{$game->id}",
                    )
                );
            }

            // Remove player from game
            $game->players()->where('user_id', $user->id)->delete();

            // Remove from Firebase room
            $this->firebaseService->removePlayerFromRoom(
                $game->firebase_room_id,
                $user->id
            );

            // If no players left, cancel the game entirely
            if ($game->fresh()->players()->count() === 0) {
                $game->update(['status' => 'cancelled']);
                $this->firebaseService->updateRoomStatus(
                    $game->firebase_room_id,
                    'cancelled'
                );
            }
        });
    }
}