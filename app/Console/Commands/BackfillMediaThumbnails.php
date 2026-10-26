<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\EventsImges;
use App\Services\MediaThumbnailService;
use Illuminate\Console\Command;

/**
 * Creates missing thumbnails for existing images. Safe to stop and re-run: items
 * that already have a thumbnail are skipped, and nothing existing is changed.
 */
class BackfillMediaThumbnails extends Command
{
    protected $signature = 'media:thumbnails
        {--queue : Dispatch a job per item instead of generating here}
        {--limit=0 : Stop after this many items (0 = all)}
        {--from-id=0 : Start after this media id}
        {--dry-run : Only count what would be generated}';

    protected $description = 'Generate missing card thumbnails from the watermarked previews (idempotent)';

    public function handle(MediaThumbnailService $thumbnails): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $seen = $generated = $existing = $skipped = 0;

        EventsImges::query()
            ->with('events')
            ->where(fn ($query) => $query->whereNull('type')->orWhere('type', '!=', 'video'))
            ->where('id', '>', max(0, (int) $this->option('from-id')))
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($thumbnails, $limit, &$seen, &$generated, &$existing, &$skipped) {
                foreach ($items as $media) {
                    if ($limit && $seen >= $limit) {
                        return false;
                    }
                    $seen++;
                    if (! $thumbnails->path($media)) {
                        $skipped++;
                    } elseif ($thumbnails->existing($media)) {
                        $existing++;
                    } elseif ($this->option('dry-run')) {
                        $generated++;
                    } elseif ($this->option('queue')) {
                        GenerateMediaThumbnailJob::dispatch($media->id);
                        $generated++;
                    } elseif ($thumbnails->generate($media)) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
                $this->line("… up to media #{$items->last()->id}");
            });

        $verb = $this->option('dry-run') ? 'would generate' : ($this->option('queue') ? 'queued' : 'generated');
        $this->info("Checked {$seen}: {$verb} {$generated}, already present {$existing}, skipped {$skipped} (no image preview or unreadable).");

        return self::SUCCESS;
    }
}
