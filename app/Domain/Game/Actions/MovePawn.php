<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Wallet\Actions\ReleaseEscrow;
use App\Jobs\FlagSuspiciousAccount;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;

class MovePawn
{
    private const HOME = -1;
    private const FINISHED = 57;
    private const TRACK_LENGTH = 52;

    private const ENTRY_OFFSETS = [
        'red' => 0,
        'green' => 13,
        'yellow' => 26,
        'blue' => 39,
    ];

    private const SAFE_SQUARES = [0, 8, 13, 21, 26, 34, 39, 47];

    public function __construct(
        private readonly FirebaseService $firebaseService,
        private readonly ReleaseEscrow $releaseEscrow,
    ) {}

    /**
     * Move one of the current player's pawns using the authoritative dice roll.
     *
     * Positions are relative to each player's colour entry square:
     * -1 = home
     *  0..51 = shared track
     * 52..56 = home column
     * 57 = finished
     *
     * @return array{game: Game, player: GamePlayer, pawn: string, from: int, to: int, captured: array<int>}
     * @throws InvalidGameStateException
     */
    public function execute(Game $game, User $user, int $pawnIndex): array
    {
        return DB::transaction(function () use ($game, $user, $pawnIndex) {
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
                throw new InvalidGameStateException('The game is not currently accepting a pawn move.');
            }

            $diceRoll = (int) $game->dice_roll;
            if ($diceRoll < 1 || $diceRoll > 6) {
                throw new InvalidGameStateException('No valid dice roll is available.');
            }

            if ($pawnIndex < 0 || $pawnIndex > 3) {
                throw new InvalidGameStateException('Invalid pawn selected.');
            }

            $players = $game->players()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $player = $players->firstWhere('user_id', $user->id);
            if (! $player) {
                throw new InvalidGameStateException('You are not a player in this game.');
            }

            $positions = array_values($player->pawn_positions ?? [self::HOME, self::HOME, self::HOME, self::HOME]);
            while (count($positions) < 4) {
                $positions[] = self::HOME;
            }

            $from = (int) $positions[$pawnIndex];
            $to = $this->calculateDestination($from, $diceRoll);

            if ($to === null) {
                throw new InvalidGameStateException('That pawn cannot make this move.');
            }

            $positions[$pawnIndex] = $to;
            $player->update(['pawn_positions' => $positions]);

            $captured = [];
            if ($to >= 0 && $to < self::TRACK_LENGTH) {
                $destinationGlobal = $this->globalTrackIndex($player->color, $to);

                if (! in_array($destinationGlobal, self::SAFE_SQUARES, true)) {
                    foreach ($players as $opponent) {
                        if ($opponent->user_id === $user->id) {
                            continue;
                        }

                        $opponentPositions = array_values(
                            $opponent->pawn_positions ?? [self::HOME, self::HOME, self::HOME, self::HOME]
                        );
                        $changed = false;

                        foreach ($opponentPositions as $index => $opponentPosition) {
                            if ($opponentPosition < 0 || $opponentPosition >= self::TRACK_LENGTH) {
                                continue;
                            }

                            if ($this->globalTrackIndex($opponent->color, (int) $opponentPosition) === $destinationGlobal) {
                                $opponentPositions[$index] = self::HOME;
                                $changed = true;
                                $captured[] = $opponent->user_id;

                                $this->firebaseService->updatePawnPosition(
                                    $game->firebase_room_id,
                                    $opponent->user_id,
                                    'p' . ($index + 1),
                                    self::HOME,
                                );
                            }
                        }

                        if ($changed) {
                            $opponent->update(['pawn_positions' => $opponentPositions]);
                        }
                    }
                }
            }

            $pawnName = 'p' . ($pawnIndex + 1);
            $this->firebaseService->updatePawnPosition(
                $game->firebase_room_id,
                $user->id,
                $pawnName,
                $to,
            );

            $this->firebaseService->recordMove(
                $game->firebase_room_id,
                $user->id,
                $diceRoll,
                $pawnName,
                $from,
                $to,
            );

            $won = count(array_filter($positions, fn (int $position) => $position === self::FINISHED)) === 4;

            if ($won) {
                $game->update([
                    'status' => 'finished',
                    'winner_id' => $user->id,
                    'phase' => 'finished',
                    'dice_roll' => null,
                    'state_version' => $game->state_version + 1,
                    'ended_at' => now(),
                ]);

                $players->each(function (GamePlayer $gamePlayer) use ($user) {
                    $gamePlayer->update([
                        'result' => $gamePlayer->user_id === $user->id ? 'winner' : 'loser',
                    ]);
                });

                $this->releaseEscrow->execute($game->fresh(), $user->wallet);
                FlagSuspiciousAccount::dispatch($user->id);
                $this->firebaseService->setWinner($game->firebase_room_id, $user->id);
            } else {
                $extraTurn = $diceRoll === 6 || count($captured) > 0;
                $nextPlayer = $extraTurn
                    ? $player
                    : $this->nextPlayer($players, $player);

                $game->update([
                    'current_turn_user_id' => $nextPlayer->user_id,
                    'turn_number' => $extraTurn ? $game->turn_number : $game->turn_number + 1,
                    'phase' => 'rolling',
                    'dice_roll' => null,
                    'state_version' => $game->state_version + 1,
                ]);

                $this->firebaseService->advanceTurn(
                    $game->firebase_room_id,
                    $nextPlayer->user_id,
                );
            }

            return [
                'game' => $game->fresh(['players']),
                'player' => $player->fresh(),
                'pawn' => $pawnName,
                'from' => $from,
                'to' => $to,
                'captured' => array_values(array_unique($captured)),
            ];
        });
    }

    private function calculateDestination(int $from, int $diceRoll): ?int
    {
        if ($from === self::FINISHED) {
            return null;
        }

        if ($from === self::HOME) {
            return $diceRoll === 6 ? 0 : null;
        }

        $destination = $from + $diceRoll;
        return $destination <= self::FINISHED ? $destination : null;
    }

    private function globalTrackIndex(string $color, int $position): int
    {
        return (self::ENTRY_OFFSETS[$color] + $position) % self::TRACK_LENGTH;
    }

    private function nextPlayer($players, GamePlayer $current): GamePlayer
    {
        $index = $players->search(fn (GamePlayer $player) => $player->id === $current->id);
        $nextIndex = ($index + 1) % $players->count();

        return $players->values()->get($nextIndex);
    }
}
