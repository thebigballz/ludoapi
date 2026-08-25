<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class MovePawnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pawn_index' => ['required', 'integer', 'between:0,3'],
        ];
    }
}
