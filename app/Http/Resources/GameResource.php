<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'firebase_room_id' => $this->firebase_room_id,
            'status'           => $this->status,
            'stake_amount'     => number_format($this->stake_amount, 2),
            'platform_fee'     => number_format($this->platform_fee, 2),
            'winner_id'        => $this->winner_id,
            'started_at'       => $this->started_at?->toDateTimeString(),
            'ended_at'         => $this->ended_at?->toDateTimeString(),
            'players'          => $this->whenLoaded('players', fn () =>
                $this->players->map(fn ($p) => [
                    'user_id' => $p->user_id,
                    'color'   => $p->color,
                    'result'  => $p->result,
                ])
            ),
        ];
    }
}