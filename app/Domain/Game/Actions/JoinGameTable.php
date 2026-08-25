<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\GameAlreadyStartedException;
use App\Domain\Game\Exceptions\TableFullException;
use App\Domain\Wallet\Actions\HoldEscrow;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Domain\Game\Actions\StartGame;
use Illuminate\Support\Facades\DB;

class JoinGameTable
{
    public function __construct(
        private readonly HoldEscrow     $holdEscrow,
        private readonly StartGame      $startGame,
    ) {}

    /**
 * @throws GameAlreadyStartedException
 * @throws TableFullException
 * @throws InsufficientBalanceException
 */
public function execute(Game $game, User $user): GamePlayer
{
    if ($game->status !== 'waiting') {
        throw new GameAlreadyStartedException();
    }

    if ($game->isFull()) {
        throw new TableFullException();
    }

    $existingPlayer = $game->players()
        ->where('user_id', $user->id)
        ->first();

    if ($existingPlayer) {
        return $existingPlayer;
    }

    return DB::transaction(function () use ($game, $user) {
        $takenColors = $game->players()
            ->pluck('color')
            ->toArray();

        $allColors = ['red', 'green', 'yellow', 'blue'];

        $color = collect($allColors)
            ->diff($takenColors)
            ->first();

        $player = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'color'   => $color,
        ]);

        $this->holdEscrow->execute(
            $user->wallet,
            $game
        );

        if ($game->fresh()->isFull()) {
            $this->startGame->execute($game);
        }

        return $player;
    });
}
}