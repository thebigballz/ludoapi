<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\RefundEscrow;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class CancelGame
{
    public function __construct(private readonly RefundEscrow $refundEscrow) {}

    /**
     * @throws InvalidGameStateException
     */
    public function execute(Game $game): Game
    {
        if (! in_array($game->status, ['waiting', 'active'])) {
            throw new InvalidGameStateException('Game cannot be cancelled.');
        }

        return DB::transaction(function () use ($game) {
            $game->update([
                'status'   => 'cancelled',
                'ended_at' => now(),
            ]);

            $game->players()->update(['result' => 'abandoned']);

            $this->refundEscrow->execute($game);

            app(FirebaseService::class)->updateRoomStatus(
                $game->firebase_room_id,
                'cancelled'
            );

            return $game->fresh();
        });
    }
}    