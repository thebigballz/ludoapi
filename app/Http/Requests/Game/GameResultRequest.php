<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class GameResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Later: verify this request comes from Firebase Cloud Functions
        // using a shared secret header
        return true;
    }

    public function rules(): array
    {
        return [
            'game_id'          => ['required', 'integer', 'exists:games,id'],
            'winner_id'        => ['required', 'integer', 'exists:users,id'],
            'firebase_room_id' => ['required', 'string'],
        ];
    }
	
	public function authorize(): bool
{
    $secret = request()->header('X-App-Secret');
    return $secret === config('app.firebase_secret');
}
}