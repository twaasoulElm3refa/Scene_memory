<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */

    public function event()
    {
        return $this->belongsTo(Events::class,'user_id');
    }

    public function user_interactions()
    {
        return $this->hasMany(user_interactions::class,'user_id');
    }

    public function whishlist()
    {
        return $this->hasMany(Wishlist::class,'user_id');
    }

    public function contacts()
    {
        return $this->hasMany(contacts::class,'user_id');
    }

    public function MediaRequest()
    {
        return $this->hasMany(MediaRequest::class,'user_id');
    }

    public function comment()
    {
        return $this->hasMany(comments::class,'user_id');
    }

    public function likes()
    {
        return $this->hasMany(Likes::class,'user_id');
    }

    public function subscription()
    {
        return $this->hasMany(Subscriptions::class,'user_id');
    }

    public function licenceType()
    {
        return $this->belongsTo(licenceType::class,'licence_type_id');
    }

    public function cart()
    {
        return $this->hsone(Cart::class,'user_id');
    }

    public function purchase()
    {
        return $this->hasMany(purchases::class,'user_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class,'user_id');
    }

    public function withdraw()
    {
        return $this->hasMany(withdraw::class,'user_id');
    }

    public function approving()
    {
        return $this->hasMany(withdraw::class,'approved_by');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransactions::class,'user_id');
    }
}

