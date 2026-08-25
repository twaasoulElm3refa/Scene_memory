<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'is_active' => 'boolean',
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
        return $this->belongsTo(Events::class, 'user_id');
    }

    public function user_interactions()
    {
        return $this->hasMany(UserInteractions::class, 'user_id');
    }

    public function whishlist()
    {
        return $this->hasMany(Wishlist::class, 'user_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contacts::class, 'user_id');
    }

    public function MediaRequest()
    {
        return $this->hasMany(MediaRequest::class, 'user_id');
    }

    public function comment()
    {
        return $this->hasMany(Comments::class, 'user_id');
    }

    public function likes()
    {
        return $this->hasMany(Likes::class, 'user_id');
    }

    public function subscription()
    {
        return $this->hasMany(Subscriptions::class, 'user_id');
    }

    public function licenceType()
    {
        return $this->belongsTo(LicenceType::class, 'licence_type_id');
    }

    public function cart()
    {
        return $this->hasOne(Cart::class, 'user_id');
    }

    public function purchase()
    {
        return $this->hasMany(Purchases::class, 'user_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    public function withdraw()
    {
        return $this->hasMany(Withdraw::class, 'user_id');
    }

    public function approving()
    {
        return $this->hasMany(Withdraw::class, 'approved_by');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransactions::class, 'user_id');
    }

    public function entitlements()
    {
        return $this->hasMany(Entitlement::class, 'user_id');
    }

    public function registrationOtps(): HasMany
    {
        return $this->hasMany(RegistrationOtp::class);
    }

    public function specialCoverageRequests(): HasMany
    {
        return $this->hasMany(SpecialCoverageRequest::class, 'user_id');
    }

    public function reviewedSpecialCoverageRequests(): HasMany
    {
        return $this->hasMany(SpecialCoverageRequest::class, 'reviewed_by');
    }
}
