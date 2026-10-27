<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacts extends Model
{
    protected $table = 'contacts';

    protected $fillable = ['name', 'email', 'subject', 'message', 'user_id', 'status', 'assigned_admin_id', 'last_message_at', 'user_read_message_id', 'submission_key', 'guest_access_token', 'guest_access_expires_at'];

    protected $hidden = ['submission_key', 'guest_access_token'];

    protected function casts(): array
    {
        return ['guest_access_token' => 'encrypted', 'guest_access_expires_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function messages()
    {
        return $this->hasMany(ContactMessage::class, 'contact_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(ContactMessage::class, 'contact_id')->latestOfMany();
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contactResponds()
    {
        return $this->hasMany(ContactResponds::class, 'contact_id');
    }
}
