<?php

namespace App\Services\Commands;

use App\Models\CloudinaryMediaAsset;
use App\Jobs\GenerateEventAiTagsJob;
use App\Services\IndependentMediaBackup;
use App\Services\UnifiedMediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupTemporaryOriginals extends Command
{
    protected $signature = 'media:cleanup-originals {--dry-run : Read-only eligibility check} {--limit=25 : Maximum assets to inspect}';

    protected $description = 'Delete only verified authenticated image originals after 72 hours and an independent backup';

    public function handle(UnifiedMediaStorage $cloud, IndependentMediaBackup $backup): int
    {
        $dry = (bool) $this->option('dry-run');
        if (! $dry && (! config('cloudinary_media.temp_original_cleanup_enabled')
            || ! config('cloudinary_media.require_verified_independent_backup'))) {
            $this->error('cleanup=disabled');
            return self::FAILURE;
        }
        $limit = min((int) config('cloudinary_media.cleanup_batch_limit'), max(1, (int) $this->option('limit')));
        $assets = CloudinaryMediaAsset::where('delivery_type', 'authenticated')->where('resource_type', 'image')
            ->whereNotNull('event_media_id')->where('cleanup_status', '!=', 'completed')
            ->orderBy('updated_at')->orderBy('id')->limit($limit)->get();
        foreach ($assets as $asset) {
            $lock = Cache::lock('media-original:'.$asset->event_media_id, 300);
            if (! $lock->get()) {
                $this->blocked($asset, 'processing_lock', $dry);
                continue;
            }
            try {
                $reason = $this->blockReason($asset, $cloud, $backup);
                if ($reason !== null) {
                    $this->blocked($asset, $reason, $dry);
                    continue;
                }
                $this->line('media_original_cleanup_eligible media_id='.$asset->event_media_id);
                if ($dry) continue;
                $media = $asset->eventMedia;
                Log::info('media_original_cleanup_started', ['media_id' => $media->id, 'asset_id' => $asset->id]);
                $asset->update(['cleanup_status' => 'deleting', 'cleanup_block_reason' => null]);
                if (! Storage::disk('media')->delete($media->local_original_path)) {
                    throw new \RuntimeException('Private original deletion failed.');
                }
                $asset->update(['cleanup_status' => 'completed', 'cleanup_completed_at' => now()]);
                Log::info('media_original_cleanup_completed', ['media_id' => $media->id, 'asset_id' => $asset->id]);
            } catch (Throwable $e) {
                $this->blocked($asset, 'cleanup_error', $dry);
                Log::warning('media_original_cleanup_failed', ['media_id' => $asset->event_media_id, 'exception_category' => get_class($e)]);
            } finally {
                $lock->release();
            }
        }
        $this->line('inspected='.$assets->count());
        return self::SUCCESS;
    }

    private function blockReason(CloudinaryMediaAsset $asset, UnifiedMediaStorage $cloud, IndependentMediaBackup $backup): ?string
    {
        $media = $asset->eventMedia;
        if (! $media || $media->type !== 'image' || $media->media_status !== 'ready'
            || $media->original_disk !== 'cloudinary' || $media->getRawOriginal('full_url') !== $asset->path
            || $asset->status !== 'ready' || $asset->delivery_type !== 'authenticated'
            || ! $asset->asset_id || ! $asset->sha256 || ! $asset->uploaded_at) return 'metadata_incomplete';
        if ($asset->uploaded_at->gt(now()->subHours((int) config('cloudinary_media.temp_original_retention_hours')))) return 'retention_window';
        $tagsJob = new GenerateEventAiTagsJob((int) $media->event_id);
        $tagsLock = Cache::lock($tagsJob->middleware()[0]->getLockKey($tagsJob), 5);
        if (! $tagsLock->get()) return 'processing_lock';
        $tagsLock->release();
        if ($asset->backup_status !== 'verified' || ! $asset->backup_verified_at || $asset->backup_sha256 !== $asset->sha256
            || ! $backup->verify($asset)) return 'backup_unverified';
        if (! is_string($media->local_original_path) || ! preg_match('#^events/full/[a-f0-9-]+\.[a-z0-9]+$#', $media->local_original_path)
            || ! Storage::disk('media')->exists($media->local_original_path)) return 'private_original_missing';
        if (hash_file('sha256', Storage::disk('media')->path($media->local_original_path)) !== $asset->sha256) return 'local_integrity_failed';
        if (! $cloud->verifyAuthenticatedOriginal($asset)) return 'cloudinary_unverified';
        return null;
    }

    private function blocked(CloudinaryMediaAsset $asset, string $reason, bool $dry): void
    {
        if (! $dry) $asset->update(['cleanup_status' => 'blocked', 'cleanup_block_reason' => $reason, 'updated_at' => now()]);
        $this->line('media_original_cleanup_blocked media_id='.$asset->event_media_id.' reason='.$reason);
        Log::info('media_original_cleanup_blocked', ['media_id' => $asset->event_media_id, 'reason' => $reason, 'dry_run' => $dry]);
    }
}
