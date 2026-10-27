<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['contact_id', 'sender_id', 'sender_type', 'message', 'idempotency_key'];

    protected $hidden = ['idempotency_key', 'legacy_kind', 'legacy_id'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
