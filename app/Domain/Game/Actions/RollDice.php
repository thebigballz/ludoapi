<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Models\Game;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;
use Random\RandomException;

class RollDice
{
    public function __construct(
        private readonly FirebaseService $firebaseService,
    ) {}

    /**
     * Roll the dice for the player whose turn it currently is.
     *
     * The database is authoritative for:
     * - game status
     * - current player
     * - game phase
     *
     * Firebase is only used to broadcast the result.
     *
     * @throws InvalidGameStateException
     * @throws RandomException
     */
    public function execute(Game $game, User $user): int
    {
        return DB::transaction(function () use ($game, $user) {
            $game = Game::whereKey($game->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($game->status !== 'active') {
                throw new InvalidGameStateException(
                    'This game is not active.'
                );
            }

            if (! $game->hasPlayer($user->id)) {
                throw new InvalidGameStateException(
                    'You are not a player in this game.'
                );
            }

            if ($game->current_turn_user_id !== $user->id) {
                throw new InvalidGameStateException(
                    'It is not your turn.'
                );
            }

            if ($game->phase !== 'rolling') {
                throw new InvalidGameStateException(
                    'The game is not currently accepting a dice roll.'
                );
            }

            $roll = random_int(1, 6);

            $game->update([
                'dice_roll'    => $roll,
                'phase'        => 'moving',
                'state_version'=> $game->state_version + 1,
            ]);

            $this->firebaseService->setDiceRoll(
                $game->firebase_room_id,
                $roll
            );

            return $roll;
        });
    }
}