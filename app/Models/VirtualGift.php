<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VirtualGift extends Model
{
    protected $guarded = [];

    protected $casts = ['translations' => 'array', 'price_minor' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    protected $appends = ['image_url', 'payment_methods'];

    public function getPaymentMethodsAttribute(): array
    {
        return in_array($this->currency, config('virtual_gifts.paypal_currencies'), true) ? ['wallet', 'paypal'] : ['wallet'];
    }

    public function getImageUrlAttribute(): ?string
    {
        return ! $this->image_path ? null : (str_starts_with($this->image_path, '/') ? $this->image_path : Storage::disk('public')->url($this->image_path));
    }
}
