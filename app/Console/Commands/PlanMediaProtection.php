<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEventVideoJob;
use App\Models\EventsImges;
use App\Services\EventTagCacheService;
use App\Services\ImageAnalysisService;
use App\Services\MediaAccessService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class PlanMediaProtection extends Command
{
    protected $signature = 'media:security-plan {--output= : New manifest basename, outside the webroot} {--copy : Copy and verify protected files without deleting sources} {--apply : Update disk references after verified copies} {--acknowledge-edge-blocking : Operator confirms legacy URL blocking and cache purge prerequisites}';

    protected $description = 'Inventory restricted legacy media; optionally copy/verify and update references. Never deletes source media.';

    public function handle(MediaAccessService $access): int
    {
        if ($this->option('apply') && (! $this->option('copy') || ! $this->option('acknowledge-edge-blocking'))) {
            $this->error('Applying references requires --copy and --acknowledge-edge-blocking. Public source copies remain and must be denied by every origin/CDN.');

            return self::FAILURE;
        }
        $name = $this->option('output') ?: 'media-'.now()->format('Ymd-His').'-'.Str::random(8).'.json';
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\.json$/D', $name)) {
            $this->error('Use a new JSON basename, without directories.');

            return self::FAILURE;
        }
        $directory = storage_path('app/security-audit');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $output = $directory.'/'.$name;
        if (file_exists($output)) {
            $this->error('Refusing to overwrite a migration manifest. Use a new output basename for a rerun.');

            return self::FAILURE;
        }
        $manifest = ['version' => 1, 'created_at' => now()->toIso8601String(), 'public_sources_deleted' => false, 'rows' => []];
        $denied = [];
        $failed = false;
        foreach (EventsImges::with('events')->where('original_disk', 'public')->orderBy('id')->lazyById(100) as $media) {
            if (! $access->restrictedOriginal($media)) {
                continue;
            }
            $old = $media->only(['full_url', 'preview_url', 'original_disk', 'preview_disk']);
            $row = ['media_id' => $media->id, 'before' => $old, 'status' => 'planned'];
            try {
                $source = $access->relativePath($media->getRawOriginal('full_url'));
                if (! $source) {
                    throw new \RuntimeException('Unsupported original path; manual investigation required.');
                }
                $previewSource = $access->relativePath($media->getRawOriginal('preview_url'));
                $blockingPaths = array_filter([$source, ! $access->isPublished($media) ? $previewSource : null]);
                foreach (array_unique($blockingPaths) as $blockingPath) {
                    $variants = [$blockingPath, '/'.$blockingPath, 'storage/'.$blockingPath, '/storage/'.$blockingPath, 'public/'.$blockingPath];
                    $others = EventsImges::with('events')->whereKeyNot($media->id)->where(function ($query) use ($variants) {
                        $query->whereIn('full_url', $variants)->orWhereIn('preview_url', $variants);
                    })->get();
                    foreach ($others as $other) {
                        if ($access->isPublished($other) && (
                            (($other->original_disk ?: 'public') === 'public' && ! $access->restrictedOriginal($other) && $access->relativePath($other->full_url) === $blockingPath) ||
                            (($other->preview_disk ?: 'public') === 'public' && $access->relativePath($other->preview_url) === $blockingPath)
                        )) {
                            $row['requires_manual_review'] = 'shared_public_path';
                            throw new \RuntimeException('Shared public path requires manual classification.');
                        }
                    }
                }
                $denied['/storage/'.$source] = true;
                $denied['/'.$source] = true;
                $previewSource = $access->relativePath($media->getRawOriginal('preview_url'));
                if (! $access->isPublished($media) && $previewSource) {
                    $denied['/storage/'.$previewSource] = true;
                    $denied['/'.$previewSource] = true;
                }
                $target = 'legacy/'.$media->id.'/original-'.$media->id.'.'.pathinfo($source, PATHINFO_EXTENSION);
                $row['after'] = ['full_url' => $target, 'original_disk' => 'media', 'preview_disk' => 'media'];
                if ($this->option('copy')) {
                    $original = $access->file($media);
                    $hash = hash_file('sha256', $original);
                    $disk = Storage::disk('media');
                    if (! $disk->exists($target)) {
                        $disk->put($target, fopen($original, 'rb'));
                    }
                    if (! hash_equals($hash, hash_file('sha256', $disk->path($target)))) {
                        throw new \RuntimeException('Original checksum mismatch; no reference changed.');
                    }
                    $row['sha256'] = $hash;
                    if ($previewSource && $previewSource !== $source) {
                        $preview = 'legacy/'.$media->id.'/preview-'.$media->id.'.'.pathinfo($previewSource, PATHINFO_EXTENSION);
                        $previewFile = $access->file($media, true);
                        if (! $disk->exists($preview)) {
                            $disk->put($preview, fopen($previewFile, 'rb'));
                        }
                        if (! hash_equals(hash_file('sha256', $previewFile), hash_file('sha256', $disk->path($preview)))) {
                            throw new \RuntimeException('Preview checksum mismatch.');
                        }
                    } elseif ($media->type === 'video') {
                        $job = new class($media->event_id, $target, false, 'media') extends ProcessEventVideoJob
                        {
                            public function render(): ?string
                            {
                                return $this->makeWatermarkedPreviewVideo($this->filePath, $this->debugId);
                            }
                        };
                        $preview = $job->render();
                        if (! $preview) {
                            throw new \RuntimeException('A safe video preview could not be generated.');
                        }
                    } else {
                        $preview = 'legacy/'.$media->id.'/preview-'.$media->id.'.jpg';
                        if (! $disk->exists($preview)) {
                            $analysis = app(ImageAnalysisService::class)->process(new UploadedFile($disk->path($target), basename($target), test: true), new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver, decodeAnimation: false, strip: true));
                            $disk->put($preview, (string) $analysis['preview_encoded']);
                        }
                    }
                    $row['after']['preview_url'] = $preview;
                    $row['preview_sha256'] = hash_file('sha256', $disk->path($preview));
                    $row['status'] = 'copied_verified';
                    if ($this->option('apply')) {
                        DB::transaction(function () use ($media, $old, $row) {
                            $locked = EventsImges::lockForUpdate()->findOrFail($media->id);
                            if ($locked->only(array_keys($old)) !== $old) {
                                throw new \RuntimeException('References changed concurrently; rerun with a new manifest.');
                            }
                            $locked->update($row['after']);
                        });
                        app(EventTagCacheService::class)->invalidateEvent((int) $media->event_id, [(int) $media->id]);
                        $row['status'] = 'references_updated';
                    }
                }
            } catch (\Throwable $exception) {
                $row['status'] = 'failed';
                $row['reason'] = $exception::class;
                $failed = true;
            }
            $manifest['rows'][] = $row;
            file_put_contents($output.'.tmp', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            chmod($output.'.tmp', 0600);
            rename($output.'.tmp', $output);
        }
        if (! file_exists($output)) {
            file_put_contents($output, json_encode($manifest, JSON_PRETTY_PRINT));
        }
        chmod($output, 0600);
        $entries = implode("\n", array_map(fn ($uri) => '"'.addcslashes($uri, '\\"$').'" 1;', array_keys($denied)));
        file_put_contents($output.'.nginx-map', $entries."\n");
        chmod($output.'.nginx-map', 0600);
        $this->info('Manifest written outside the webroot: '.$output);
        $this->warn('No public source was deleted. References/private copies alone DO NOT close old static URLs. Verify all origins, aliases and caches before publication.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
