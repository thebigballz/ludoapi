<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'kyc_status' => $this->kyc_status,
            'avatar'     => $this->avatar,
            'wallet'     => $this->whenLoaded('wallet', fn () => [
                'balance' => number_format($this->wallet->balance, 2),
            ]),
            'created_at' => $this->created_at->toDateString(),
        ];
    }
}