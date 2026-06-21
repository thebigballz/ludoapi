<?php

namespace App\Domain\Game\DTOs;

readonly class GameResultDTO
{
    public function __construct(
        public int $gameId,
        public int $winnerId,
        public string $firebaseRoomId,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            gameId:        $data['game_id'],
            winnerId:      $data['winner_id'],
            firebaseRoomId: $data['firebase_room_id'],
        );
    }
}