<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityNomination extends Model
{
    protected $table = 'city_nominations';

    protected $guarded = [];

    protected $casts = [
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'bbox_min_lat' => 'decimal:7',
        'bbox_max_lat' => 'decimal:7',
        'bbox_min_lng' => 'decimal:7',
        'bbox_max_lng' => 'decimal:7',
    ];

    public function city()
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }
}
