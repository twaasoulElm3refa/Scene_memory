<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
