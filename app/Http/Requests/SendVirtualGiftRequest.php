<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendVirtualGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['gift_id' => 'required|integer|min:1', 'event_id' => 'required|integer|min:1', 'comment_id' => 'nullable|integer|min:1', 'quantity' => 'required|integer|min:1|max:100', 'idempotency_key' => 'required|string|min:16|max:100', 'expected_unit_price_minor' => 'sometimes|integer|min:1|max:99999999', 'expected_currency' => 'required_with:expected_unit_price_minor|string|size:3', 'recipient_user_id' => 'prohibited', 'price_minor' => 'prohibited', 'gross_amount_minor' => 'prohibited', 'payment_status' => 'prohibited'];
    }
}
