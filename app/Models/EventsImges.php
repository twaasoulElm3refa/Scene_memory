<?php

namespace App\Models;

use App\Services\MediaAccessService;
use Illuminate\Database\Eloquent\Model;

class EventsImges extends Model
{
    protected $table = 'events_images';

    protected $guarded = [];

    public function toArray(): array
    {
        $data = parent::toArray();
        $access = app(MediaAccessService::class);
        $viewer = request()->user('sanctum');
        $preview = $access->previewUrl($this, ! $access->isPublished($this) && $access->ownerOrAdmin($this, $viewer));
        // Preserve the API's existing display keys, never its internal original path.
        foreach (['full_url', 'preview_url', 'url', 'video'] as $key) {
            if (array_key_exists($key, $data) || in_array($key, ['full_url', 'preview_url'], true)) {
                $data[$key] = $preview;
            }
        }
        unset($data['original_disk'], $data['preview_disk'], $data['events']);

        return $data;
    }

    public function events()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function MediaRequest()
    {
        return $this->hasMany(MediaRequest::class, 'image_id');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'image_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tags::class, 'images_tags', 'events_imges_id', 'tags_id');
    }

    public function images_tags()
    {
        return $this->hasMany(ImagesTags::class, 'events_imges_id');
    }

    public function translations()
    {
        return $this->hasMany(ImageTranslations::class, 'image_id');
    }

    public function translation()
    {
        return $this->hasOne(ImageTranslations::class, 'image_id')
            ->where('locale', app()->getLocale());
    }
}
