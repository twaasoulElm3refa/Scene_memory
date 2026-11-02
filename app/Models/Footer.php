<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Footer extends Model
{
   protected $table='footers';
   protected $guarded = [];

   public function getLogoAttribute(?string $value): ?string
   {
       $storage = app(\App\Services\UnifiedMediaStorage::class);
       return $storage->isCloudinary($value) ? $storage->url($value) : $value;
   }


}
