<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone'  => ['required', 'string', 'regex:/^254[0-9]{9}$/'],
            'amount' => [
                'required',
                'numeric',
                'min:10',
                'max:' . config('ludo.max_daily_withdrawal'),
            ],
        ];
    }
}