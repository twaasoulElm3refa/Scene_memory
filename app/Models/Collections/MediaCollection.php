<?php

namespace App\Models\Collections;

use App\Support\MediaParents;
use Illuminate\Database\Eloquent\Collection;

/** Media collection that loads every item's event in one query before serializing. */
class MediaCollection extends Collection
{
    public function toArray()
    {
        MediaParents::prime($this->items);

        return parent::toArray();
    }

    public function jsonSerialize(): array
    {
        MediaParents::prime($this->items);

        return parent::jsonSerialize();
    }
}
