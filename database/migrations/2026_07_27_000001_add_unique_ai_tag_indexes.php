<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeAndMergeTags();
        $this->deduplicateEventTags();
        $this->deduplicateImageTags();

        if (! Schema::hasIndex('tags', 'tags_slug_unique')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->unique('slug', 'tags_slug_unique');
            });
        }

        if (! Schema::hasIndex('event__tags', 'event_tags_event_tag_unique')) {
            Schema::table('event__tags', function (Blueprint $table) {
                $table->unique(
                    ['event_id', 'tag_id'],
                    'event_tags_event_tag_unique'
                );
            });
        }

        if (! Schema::hasIndex('images_tags', 'images_tags_image_tag_unique')) {
            Schema::table('images_tags', function (Blueprint $table) {
                $table->unique(
                    ['events_imges_id', 'tags_id'],
                    'images_tags_image_tag_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('images_tags', 'images_tags_image_tag_unique')) {
            Schema::table('images_tags', function (Blueprint $table) {
                $table->dropUnique('images_tags_image_tag_unique');
            });
        }

        if (Schema::hasIndex('event__tags', 'event_tags_event_tag_unique')) {
            Schema::table('event__tags', function (Blueprint $table) {
                $table->dropUnique('event_tags_event_tag_unique');
            });
        }

        if (Schema::hasIndex('tags', 'tags_slug_unique')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropUnique('tags_slug_unique');
            });
        }
    }

    private function normalizeAndMergeTags(): void
    {
        $keepers = [];

        DB::table('tags')
            ->orderBy('id')
            ->get()
            ->each(function (object $tag) use (&$keepers): void {
                $name = preg_replace('/\s+/u', ' ', trim((string) $tag->name));
                $slug = $name !== ''
                    ? Str::slug($name)
                    : trim((string) $tag->slug);

                if ($slug === '') {
                    $slug = $name !== ''
                        ? 'tag-'.md5(mb_strtolower($name))
                        : 'tag-record-'.$tag->id;
                }

                if (! isset($keepers[$slug])) {
                    $keepers[$slug] = $tag;

                    DB::table('tags')
                        ->where('id', $tag->id)
                        ->update([
                            'name' => $name !== '' ? $name : $tag->name,
                            'slug' => $slug,
                        ]);

                    return;
                }

                $keeper = $keepers[$slug];
                $this->moveTagReferences((int) $tag->id, (int) $keeper->id);

                if ($keeper->deleted_at !== null && $tag->deleted_at === null) {
                    DB::table('tags')
                        ->where('id', $keeper->id)
                        ->update(['deleted_at' => null]);
                    $keeper->deleted_at = null;
                }

                DB::table('tags')->where('id', $tag->id)->delete();
            });
    }

    private function moveTagReferences(int $duplicateTagId, int $keeperTagId): void
    {
        DB::table('event__tags')
            ->where('tag_id', $duplicateTagId)
            ->orderBy('id')
            ->get()
            ->each(function (object $pivot) use ($keeperTagId): void {
                $existing = DB::table('event__tags')
                    ->where('event_id', $pivot->event_id)
                    ->where('tag_id', $keeperTagId)
                    ->orderBy('id')
                    ->first();

                if ($existing !== null) {
                    if ($existing->deleted_at !== null && $pivot->deleted_at === null) {
                        DB::table('event__tags')
                            ->where('id', $existing->id)
                            ->update(['deleted_at' => null]);
                    }

                    DB::table('event__tags')->where('id', $pivot->id)->delete();

                    return;
                }

                DB::table('event__tags')
                    ->where('id', $pivot->id)
                    ->update(['tag_id' => $keeperTagId]);
            });

        DB::table('images_tags')
            ->where('tags_id', $duplicateTagId)
            ->orderBy('id')
            ->get()
            ->each(function (object $pivot) use ($keeperTagId): void {
                $exists = DB::table('images_tags')
                    ->where('events_imges_id', $pivot->events_imges_id)
                    ->where('tags_id', $keeperTagId)
                    ->exists();

                if ($exists) {
                    DB::table('images_tags')->where('id', $pivot->id)->delete();

                    return;
                }

                DB::table('images_tags')
                    ->where('id', $pivot->id)
                    ->update(['tags_id' => $keeperTagId]);
            });
    }

    private function deduplicateEventTags(): void
    {
        $duplicates = DB::table('event__tags')
            ->select('event_id', 'tag_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereNotNull('event_id')
            ->whereNotNull('tag_id')
            ->groupBy('event_id', 'tag_id')
            ->having('aggregate', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('event__tags')
                ->where('event_id', $duplicate->event_id)
                ->where('tag_id', $duplicate->tag_id)
                ->orderBy('id')
                ->get();
            $keeper = $rows->first();

            if ($rows->contains(fn (object $row) => $row->deleted_at === null)) {
                DB::table('event__tags')
                    ->where('id', $keeper->id)
                    ->update(['deleted_at' => null]);
            }

            DB::table('event__tags')
                ->whereIn('id', $rows->skip(1)->pluck('id'))
                ->delete();
        }
    }

    private function deduplicateImageTags(): void
    {
        $duplicates = DB::table('images_tags')
            ->select('events_imges_id', 'tags_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereNotNull('events_imges_id')
            ->whereNotNull('tags_id')
            ->groupBy('events_imges_id', 'tags_id')
            ->having('aggregate', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('images_tags')
                ->where('events_imges_id', $duplicate->events_imges_id)
                ->where('tags_id', $duplicate->tags_id)
                ->orderBy('id')
                ->pluck('id');

            DB::table('images_tags')
                ->whereIn('id', $ids->skip(1))
                ->delete();
        }
    }
};
