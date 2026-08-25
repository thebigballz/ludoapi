<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkipTurn
{
    private const HOME = -1;
    private const FINISHED = 57;

    public function __construct(
        private readonly FirebaseService $firebaseService,
    ) {}

    /**
     * Advance the current turn only when the authoritative dice roll
     * leaves the player with no legal pawn move.
     *
     * @throws InvalidGameStateException
     */
    public function execute(Game $game, User $user): Game
    {
        return DB::transaction(function () use ($game, $user) {
            $game = Game::whereKey($game->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($game->status !== 'active') {
                throw new InvalidGameStateException('This game is not active.');
            }

            if (! $game->hasPlayer($user->id)) {
                throw new InvalidGameStateException('You are not a player in this game.');
            }

            if ($game->current_turn_user_id !== $user->id) {
                throw new InvalidGameStateException('It is not your turn.');
            }

            if ($game->phase !== 'moving') {
                throw new InvalidGameStateException('The game is not currently accepting a turn skip.');
            }

            $diceRoll = (int) $game->dice_roll;
            if ($diceRoll < 1 || $diceRoll > 6) {
                throw new InvalidGameStateException('No valid dice roll is available.');
            }

            $players = $game->players()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $player = $players->firstWhere('user_id', $user->id);
            if (! $player) {
                throw new InvalidGameStateException('You are not a player in this game.');
            }

            if ($this->hasLegalMove($player, $diceRoll)) {
                throw new InvalidGameStateException('A legal pawn move is available.');
            }

            $nextPlayer = $this->nextPlayer($players, $player);
            $game->update([
                'current_turn_user_id' => $nextPlayer->user_id,
                'turn_number' => $game->turn_number + 1,
                'phase' => 'rolling',
                'dice_roll' => null,
                'state_version' => $game->state_version + 1,
            ]);

            $this->firebaseService->advanceTurn(
                $game->firebase_room_id,
                $nextPlayer->user_id,
                $game->turn_number,
            );

            return $game->fresh(['players']);
        });
    }

    private function hasLegalMove(GamePlayer $player, int $diceRoll): bool
    {
        $positions = array_values(
            $player->pawn_positions ?? [self::HOME, self::HOME, self::HOME, self::HOME]
        );

        while (count($positions) < 4) {
            $positions[] = self::HOME;
        }

        foreach ($positions as $position) {
            $position = (int) $position;

            if ($position === self::FINISHED) {
                continue;
            }

            if ($position === self::HOME) {
                if ($diceRoll === 6) {
                    return true;
                }
                continue;
            }

            if ($position + $diceRoll <= self::FINISHED) {
                return true;
            }
        }

        return false;
    }

    private function nextPlayer(Collection $players, GamePlayer $current): GamePlayer
    {
        $index = $players->search(
            fn (GamePlayer $player) => $player->id === $current->id
        );

        return $players->values()->get(($index + 1) % $players->count());
    }
}
