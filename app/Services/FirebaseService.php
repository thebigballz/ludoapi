<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;

class FirebaseService
{
    public function __construct(private readonly Database $database) {}

    // -------------------------------------------------------
    // Room management
    // -------------------------------------------------------

    public function createRoom(int $gameId, string $roomId, float $stakeAmount): void
    {
        $this->roomRef($roomId)->set([
            'meta' => [
                'game_id'      => $gameId,
                'status'       => 'waiting',
                'stake_amount' => $stakeAmount,
                'created_at'   => $this->timestamp(),
            ],
            'players' => [],
            'state'   => [
                'current_turn' => null,
                'turn_number'  => 0,
                'dice_roll'    => null,
                'phase'        => 'waiting',
            ],
            'pawns' => [],
            'moves' => [],
            'chat'  => [],
        ]);
    }

    public function addPlayerToRoom(string $roomId, int $userId, string $name, string $color): void
    {
        $this->roomRef($roomId)
            ->getChild("players/user_{$userId}")
            ->set([
                'user_id'      => $userId,
                'name'         => $name,
                'color'        => $color,
                'is_ready'     => false,
                'is_connected' => true,
                'last_seen'    => $this->timestamp(),
            ]);

        // Initialise pawns at home position
        $this->roomRef($roomId)
            ->getChild("pawns/user_{$userId}")
            ->set([
                'p1' => -1,
                'p2' => -1,
                'p3' => -1,
                'p4' => -1,
            ]);
    }
	
	public function removePlayerFromRoom(string $roomId, int $userId): void
{
    $this->roomRef($roomId)
        ->getChild("players/user_{$userId}")
        ->remove();

    $this->roomRef($roomId)
        ->getChild("pawns/user_{$userId}")
        ->remove();
}

    public function startRoom(string $roomId, int $firstPlayerUserId): void
    {
        $this->roomRef($roomId)->update([
            'meta/status'          => 'active',
            'state/phase'          => 'rolling',
            'state/current_turn'   => "user_{$firstPlayerUserId}",
            'state/turn_number'    => 1,
        ]);
    }

    public function updateRoomStatus(string $roomId, string $status): void
    {
        $this->roomRef($roomId)->getChild('meta/status')->set($status);
    }

    public function deleteRoom(string $roomId): void
    {
        $this->roomRef($roomId)->remove();
    }

    // -------------------------------------------------------
    // Game state
    // -------------------------------------------------------

    public function setDiceRoll(string $roomId, int $roll): void
    {
        $this->roomRef($roomId)->update([
            'state/dice_roll' => $roll,
            'state/phase'     => 'moving',
        ]);
    }

    public function updatePawnPosition(string $roomId, int $userId, string $pawn, int $position): void
    {
        $this->roomRef($roomId)
            ->getChild("pawns/user_{$userId}/{$pawn}")
            ->set($position);
    }

    public function recordMove(
        string $roomId,
        int    $userId,
        int    $diceRoll,
        string $pawn,
        int    $from,
        int    $to
    ): void {
        $this->roomRef($roomId)
            ->getChild('moves')
            ->push([
                'user_id'   => $userId,
                'dice_roll' => $diceRoll,
                'pawn'      => $pawn,
                'from'      => $from,
                'to'        => $to,
                'timestamp' => $this->timestamp(),
            ]);
    }

    public function advanceTurn(string $roomId, int $nextPlayerUserId): void
    {
        $this->roomRef($roomId)->update([
            'state/current_turn' => "user_{$nextPlayerUserId}",
            'state/dice_roll'    => null,
            'state/phase'        => 'rolling',
        ]);

        // Increment turn number
        $current = $this->roomRef($roomId)
            ->getChild('state/turn_number')
            ->getValue() ?? 0;

        $this->roomRef($roomId)
            ->getChild('state/turn_number')
            ->set($current + 1);
    }

    public function setWinner(string $roomId, int $userId): void
    {
        $this->roomRef($roomId)->update([
            'meta/status'    => 'finished',
            'state/phase'    => 'finished',
            'state/winner'   => "user_{$userId}",
        ]);
    }

    // -------------------------------------------------------
    // Presence
    // -------------------------------------------------------

    public function setPlayerConnected(string $roomId, int $userId, bool $connected): void
    {
        $this->roomRef($roomId)->update([
            "players/user_{$userId}/is_connected" => $connected,
            "players/user_{$userId}/last_seen"    => $this->timestamp(),
        ]);
    }

    // -------------------------------------------------------
    // Chat
    // -------------------------------------------------------

    public function sendChatMessage(string $roomId, int $userId, string $name, string $message): void
    {
        $this->roomRef($roomId)
            ->getChild('chat')
            ->push([
                'user_id'   => $userId,
                'name'      => $name,
                'message'   => $message,
                'timestamp' => $this->timestamp(),
            ]);
    }

    // -------------------------------------------------------
    // Read
    // -------------------------------------------------------

    public function getRoom(string $roomId): ?array
    {
        $value = $this->roomRef($roomId)->getValue();
        return $value ? (array) $value : null;
    }

    public function getRoomState(string $roomId): ?array
    {
        $value = $this->roomRef($roomId)->getChild('state')->getValue();
        return $value ? (array) $value : null;
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function roomRef(string $roomId): Reference
    {
        return $this->database->getReference("games/{$roomId}");
    }

    private function timestamp(): int
    {
        return now()->getTimestampMs();
    }
}