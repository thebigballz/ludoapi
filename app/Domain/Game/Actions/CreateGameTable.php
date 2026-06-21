<?php

namespace App\Domain\Game\Actions;

use App\Domain\Game\DTOs\CreateTableDTO;
use App\Models\Game;
use App\Services\FirebaseService;
use Illuminate\Support\Str;

class CreateGameTable
{
    public function __construct(private readonly FirebaseService $firebaseService) {}

    public function execute(CreateTableDTO $dto): Game
    {
        $stakeAmount = $dto->stakeAmount;
        $platformFee = round(
            $stakeAmount * config('ludo.players_per_game') * (config('ludo.platform_fee_percent') / 100),
            2
        );

        $roomId = 'room_' . Str::uuid();

        $game = Game::create([
            'firebase_room_id' => $roomId,
            'status'           => 'waiting',
            'stake_amount'     => $stakeAmount,
            'platform_fee'     => $platformFee,
        ]);

        $this->firebaseService->createRoom($game->id, $roomId, $stakeAmount);

        return $game;
    }
}