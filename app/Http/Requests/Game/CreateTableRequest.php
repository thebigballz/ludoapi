<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stake_amount' => [
                'required',
                'numeric',
                'min:' . config('ludo.min_stake'),
                'max:' . config('ludo.max_stake'),
            ],
        ];
    }
}