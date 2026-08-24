<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\DTOs\GameResultDTO;
use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\ReleaseEscrow;
use App\Jobs\FlagSuspiciousAccount;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordGameResult
{
    public function __construct(private readonly ReleaseEscrow $releaseEscrow) {}

    /**
     * @throws InvalidGameStateException
     */
    public function execute(GameResultDTO $dto): Game
    {
        return DB::transaction(function () use ($dto) {
            $game = Game::where('id', $dto->gameId)
                ->where('firebase_room_id', $dto->firebaseRoomId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($game->status !== 'active') {
                throw new InvalidGameStateException('Game is not active.');
            }

            if (! $game->hasPlayer($dto->winnerId)) {
                throw new InvalidGameStateException('Winner is not a player in this game.');
            }

            $winner = User::findOrFail($dto->winnerId);

            $game->update([
                'status'    => 'finished',
                'winner_id' => $winner->id,
                'ended_at'  => now(),
            ]);

            $game->players()
                ->where('user_id', $winner->id)
                ->update(['result' => 'winner']);

            $game->players()
                ->where('user_id', '!=', $winner->id)
                ->update(['result' => 'loser']);

            $this->releaseEscrow->execute($game, $winner->wallet);

            FlagSuspiciousAccount::dispatch($winner->id);

            return $game->fresh();
        });
    }
}
