<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'status'         => $this->status,
            'amount'         => number_format($this->amount, 2),
            'balance_before' => number_format($this->balance_before, 2),
            'balance_after'  => number_format($this->balance_after, 2),
            'reference'      => $this->reference,
            'description'    => $this->description,
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}