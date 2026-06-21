<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'balance'         => number_format($this->balance, 2),
            'total_deposited' => number_format($this->total_deposited, 2),
            'total_withdrawn' => number_format($this->total_withdrawn, 2),
            'total_won'       => number_format($this->total_won, 2),
            'total_lost'      => number_format($this->total_lost, 2),
        ];
    }
}