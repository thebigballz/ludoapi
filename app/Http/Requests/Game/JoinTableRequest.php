<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class JoinTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game_id' => ['required', 'integer', 'exists:games,id'],
        ];
    }
}