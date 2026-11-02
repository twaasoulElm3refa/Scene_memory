<?php

namespace App\Console\Commands;

use App\Jobs\PromotePublishedMediaPreviews;
use App\Models\Events;
use App\Models\EventsImges;
use App\Services\UnifiedMediaStorage;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

class DiagnoseCloudinaryEvent extends Command
{
    protected $signature = 'media:cloudinary-event {eventId : Existing event ID} {--promote : Retry only this published event\'s local previews}';

    protected $description = 'Show safe Cloudinary, queue and media state for one event; optionally retry its preview promotion';

    public function handle(UnifiedMediaStorage $storage): int
    {
        $eventId = filter_var($this->argument('eventId'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $eventId) return self::INVALID;

        $url = (string) config('cloudinary_media.url');
        try {
            $cloudName = $url !== '' ? (new Cloudinary($url))->configuration->cloud->cloudName : null;
        } catch (Throwable $e) {
            $cloudName = null;
        }
        $git = new Process(['git', '-C', base_path(), 'rev-parse', '--short', 'HEAD']);
        $git->run();
        $this->line('deployment_commit='.($git->isSuccessful() ? trim($git->getOutput()) : 'unknown'));
        $this->line('app_url_host='.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'unknown'));
        $this->line('config_cached='.(app()->configurationIsCached() ? 'yes' : 'no'));
        $this->line('cloudinary_credentials='.($url !== '' ? 'configured' : 'missing'));
        $this->line('cloud_name='.($cloudName && preg_match('/^[a-zA-Z0-9_-]+$/', $cloudName) ? $cloudName : 'missing'));
        $this->line('images_enabled='.($storage->enabled('image') ? 'true' : 'false'));
        $this->line('videos_enabled='.($storage->enabled('video') ? 'true' : 'false'));
        $this->line('local_fallback='.(config('cloudinary_media.allow_local_fallback') ? 'true' : 'false'));
        $this->line('cloudinary_sdk='.(class_exists(\Cloudinary\Cloudinary::class) ? 'loaded' : 'missing'));
        try {
            $this->line('cloudinary_migration='.(Schema::hasTable('cloudinary_media_assets') && Schema::hasColumn('events_images', 'fallback_preview_url') ? 'present' : 'missing'));
        } catch (Throwable $e) {
            $this->error('database=unavailable('.class_basename($e).')');
            return self::FAILURE;
        }
        $this->line('queue_connection='.config('queue.default'));
        try {
            $this->line('default_queue_depth='.Queue::size('default'));
        } catch (Throwable $e) {
            $this->line('default_queue_depth=unavailable('.class_basename($e).')');
        }

        try {
            $event = Events::find($eventId);
        } catch (Throwable $e) {
            $this->error('database=unavailable('.class_basename($e).')');
            return self::FAILURE;
        }
        if (! $event) {
            $this->error('event=missing');
            return self::FAILURE;
        }
        $this->line('event_id='.$eventId);
        $this->line('event_published='.($event->is_active ? 'true' : 'false'));
        if (Schema::hasTable('event_request_creates')) {
            $status = DB::table('event_request_creates')->where('event_id', $eventId)->latest('id')->value('status');
            $this->line('moderation_status='.$status ?: 'none');
        }
        if (Schema::hasTable('job_batches')) {
            $batch = DB::table('job_batches')->where('name', 'event-media:'.$eventId)->latest('created_at')->first();
            $this->line('media_batch='.($batch ? 'present' : 'missing'));
            if ($batch) {
                $this->line('media_batch_pending_jobs='.$batch->pending_jobs);
                $this->line('media_batch_failed_jobs='.$batch->failed_jobs);
            }
        }
        $this->reportMedia($eventId, $storage);

        if ($this->option('promote')) {
            if (! $event->is_active || ! $storage->enabled('image')) {
                $this->error('promotion=skipped (event unpublished or images disabled)');
                return self::FAILURE;
            }
            try {
                (new PromotePublishedMediaPreviews($eventId))->handle($storage);
            } catch (Throwable $e) {
                $this->error('promotion=failed('.class_basename($e).')');
                return self::FAILURE;
            }
            $remaining = EventsImges::where('event_id', $eventId)->where('type', 'image')
                ->where('is_active', true)->where('preview_disk', 'media')->whereNotNull('preview_url')->count();
            $this->line('promotion='.($remaining === 0 ? 'complete' : 'incomplete'));
            $this->reportMedia($eventId, $storage);
            if ($remaining > 0) return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportMedia(int $eventId, UnifiedMediaStorage $storage): void
    {
        $media = EventsImges::where('event_id', $eventId)->orderBy('id')->get();
        $this->line('media_count='.$media->count());
        foreach ($media as $item) {
            $this->line(sprintf('media_id=%d type=%s paid=%s original_disk=%s preview_disk=%s preview_cloudinary=%s fallback_present=%s',
                $item->id, $item->type, $item->price > 0 ? 'true' : 'false',
                $item->original_disk ?: 'legacy', $item->preview_disk ?: 'legacy',
                $storage->isCloudinary($item->getRawOriginal('preview_url')) ? 'true' : 'false',
                $item->fallback_preview_url ? 'true' : 'false'));
        }
    }
}
