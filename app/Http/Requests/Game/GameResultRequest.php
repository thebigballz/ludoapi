<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class GameResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = (string) $this->header('X-App-Secret', '');
        $expected = (string) config('app.firebase_secret', '');

        if ($secret === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $secret);
    }

    public function rules(): array
    {
        return [
            'game_id'          => ['required', 'integer', 'exists:games,id'],
            'winner_id'        => ['required', 'integer', 'exists:users,id'],
            'firebase_room_id' => ['required', 'string'],
        ];
    }
}
