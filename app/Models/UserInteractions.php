<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInteractions extends Model
{
    /** @use HasFactory<\Database\Factories\UserInteractionsFactory> */
    use HasFactory;

    protected $table="user_interactions";
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event()
    {
        return $this->belongsTo(Events::class,'event_id');
    }
}
