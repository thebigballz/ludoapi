<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Models\Game;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;

class StartGame
{
    public function __construct(
        private readonly FirebaseService $firebaseService,
    ) {}

    /**
     * Start a full game and establish its authoritative initial state.
     *
     * @throws InvalidGameStateException
     */
    public function execute(Game $game): Game
    {
        return DB::transaction(function () use ($game) {
            $game = Game::whereKey($game->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($game->status !== 'waiting') {
                throw new InvalidGameStateException(
                    'Game is not waiting to start.'
                );
            }

            if (! $game->isFull()) {
                throw new InvalidGameStateException(
                    'Game cannot start until all player slots are filled.'
                );
            }

            $players = $game->players()
                ->orderBy('id')
                ->get();

            if ($players->isEmpty()) {
                throw new InvalidGameStateException(
                    'Game has no players.'
                );
            }

            $firstPlayer = $players->random();

            $game->update([
                'status'               => 'active',
                'current_turn_user_id' => $firstPlayer->user_id,
                'turn_number'          => 1,
                'phase'                => 'rolling',
                'dice_roll'            => null,
                'state_version'        => 1,
                'started_at'           => now(),
            ]);

            $this->firebaseService->startRoom(
                $game->firebase_room_id,
                $firstPlayer->user_id,
            );

            return $game->fresh(['players']);
        });
    }
}