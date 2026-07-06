<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Events extends Model
{
    /** @use HasFactory<\Database\Factories\EventsFactory> */
    use HasFactory , Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'city_id' => (int) $this->city_id,
            'sub_category_id' => (int) $this->sub_category_id,
            'start_date' => $this->start_date?->timestamp,
            'end_date' => $this->end_date?->timestamp,
            'is_active' => (bool) $this->is_active,
        ];
    }

    protected $table = 'events';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_trending' => 'boolean',
    ];
    public function city()
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event_tags()
    {
        return $this->hasMany(Event_Tags::class, 'event_id');
    }

    public function images()
    {
        return $this->hasMany(EventsImges::class, 'event_id');
    }

    public function sub_categorey()
    {
        return $this->belongsTo(SubCategorey::class, 'sub_categorey_id');
    }

    public function requests()
    {
        return $this->hasOne(EventRequestCreate::class, 'event_id');
    }

    public function MediaRequest()
    {
        return $this->hasMany(MediaRequest::class, 'event_id');
    }

    public function photos()
    {
        return $this->hasMany(EventPhotos::class,'event_id');
    }

    public function firstImage()
    {
        return $this->hasOne(EventsImges::class,'event_id');
    }

    public function comments()
    {
        return $this->hasMany(Comments::class,'event_id');
    }

    public function likes()
    {
        return $this->hasMany(Likes::class,'event_id');
    }

      public function translations()
    {
        return $this->hasMany(EventTranslations::class, 'event_id');
    }

    public function translation()
    {
        return $this->hasOne(EventTranslations::class, 'event_id')
            ->where('locale', app()->getLocale());
    }

     public function adminTranslation()
    {
        return $this->hasOne(EventTranslations::class, 'event_id')
            ->where('locale', 'ar');
    }

    public function views()
    {
        return $this->hasMany(EventViews::class,'event_id');
    }

    public function interactions()
    {
        return $this->hasMany(UserInteractions::class,'event_id');
    }

}
