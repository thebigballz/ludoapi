<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\GameAlreadyStartedException;
use App\Domain\Game\Exceptions\TableFullException;
use App\Domain\Wallet\Actions\HoldEscrow;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;

class JoinGameTable
{
    public function __construct(
        private readonly HoldEscrow     $holdEscrow,
        private readonly FirebaseService $firebaseService,
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

    // Already in this game — return existing player record
    $existingPlayer = $game->players()
        ->where('user_id', $user->id)
        ->first();

    if ($existingPlayer) {
        return $existingPlayer;
    }
        if ($game->status !== 'waiting') {
            throw new GameAlreadyStartedException();
        }

        if ($game->isFull()) {
            throw new TableFullException();
        }

        return DB::transaction(function () use ($game, $user) {
            $takenColors = $game->players()->pluck('color')->toArray();
            $allColors   = ['red', 'green', 'yellow', 'blue'];
            $color       = collect($allColors)->diff($takenColors)->first();

            $player = GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $user->id,
                'color'   => $color,
            ]);

            // Add player to Firebase room
            $this->firebaseService->addPlayerToRoom(
                roomId: $game->firebase_room_id,
                userId: $user->id,
                name:   $user->name,
                color:  $color,
            );

            // Lock stake in escrow
            $this->holdEscrow->execute($user->wallet, $game);

            // Auto-start when table is full
			$playerCount = $game->players()->count();
			$maxPlayers  = config('ludo.players_per_game');

			if ($playerCount >= $maxPlayers) {
				$game->update([
					'status'     => 'active',
					'started_at' => now(),
			]);

			$firstPlayer = $game->players()->inRandomOrder()->first();
			$this->firebaseService->startRoom(
			$game->firebase_room_id,
			$firstPlayer->user_id
    );
}
            return $player;
        });
    }
}