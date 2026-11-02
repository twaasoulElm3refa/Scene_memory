<?php

namespace App\Services;

use App\Models\CloudinaryMediaAsset;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Public derivatives only. Marketplace originals always remain on the private media disk. */
class UnifiedMediaStorage
{
    public function enabled(string $type): bool
    {
        return (bool) config("cloudinary_media.{$type}s_enabled") && (bool) config('cloudinary_media.url');
    }

    public function isCloudinary(?string $path): bool
    {
        return is_string($path) && preg_match('#^cloudinary/(image|video)/scemory/(?:[a-z0-9-]+/)+[a-f0-9-]+\.[a-z0-9]+$#', $path) === 1;
    }

    public function upload(UploadedFile|string $source, string $type, string $folder = 'public'): string
    {
        if (! in_array($type, ['image', 'video'], true)) {
            throw new RuntimeException('Unsupported media type.');
        }
        $file = $source instanceof UploadedFile ? $source->getRealPath() : $source;
        if (! $file || ! is_file($file)) throw new RuntimeException('Media source is unavailable.');
        $bytes = filesize($file);
        $limit = (int) config("cloudinary_media.max_{$type}_mb") * 1048576;
        if ($bytes === false || $bytes < 1 || $bytes > $limit) {
            throw new RuntimeException('Media exceeds the configured upload limit.');
        }
        return Cache::lock('cloudinary:upload-budget', 1200)->block(30, fn () => $this->uploadLocked($file, $bytes, $type, $folder));
    }

    private function uploadLocked(string $file, int $bytes, string $type, string $folder): string
    {
        $this->assertBudget($bytes);
        $publicId = 'scemory/'.strtolower(trim($folder, '/')).'/'.Str::uuid();
        $options = ['resource_type' => $type, 'public_id' => $publicId, 'overwrite' => false];
        if ($type === 'video' && $bytes > 10 * 1048576) {
            $options['chunk_size'] = 6 * 1048576;
        }
        $result = $this->sendUpload($file, $options);
        $format = strtolower((string) ($result['format'] ?? ''));
        if (! preg_match('/^[a-z0-9]{2,10}$/', $format) || empty($result['public_id'])) {
            $this->sendDestroy($publicId, $type);
            throw new RuntimeException('Cloudinary returned incomplete upload metadata.');
        }
        $path = "cloudinary/{$type}/{$result['public_id']}.{$format}";
        try {
            CloudinaryMediaAsset::create([
                'path' => $path, 'asset_id' => $result['asset_id'] ?? null,
                'public_id' => $result['public_id'], 'resource_type' => $type,
                'format' => $format, 'version' => $result['version'] ?? null,
                'bytes' => $result['bytes'] ?? $bytes, 'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null, 'duration' => $result['duration'] ?? null,
                'status' => 'ready',
            ]);
        } catch (Throwable $e) {
            $this->sendDestroy($result['public_id'], $type);
            throw $e;
        }
        return $path;
    }

    public function publicImage(UploadedFile $file, string $folder): string
    {
        $local = $file->store($folder, 'public');
        if (! $local) throw new RuntimeException('Unable to retain the local media copy.');
        if (! $this->enabled('image')) {
            return $local;
        }
        try {
            $path = $this->upload($file, 'image', $folder);
            CloudinaryMediaAsset::where('path', $path)->update(['local_path' => $local]);
            Cache::forget('cloudinary:asset:'.sha1($path));
            return $path;
        } catch (Throwable $e) {
            if (! config('cloudinary_media.allow_local_fallback')) {
                Storage::disk('public')->delete($local);
                throw $e;
            }
            Log::warning('cloudinary_image_fallback', ['reason' => $e->getMessage()]);
            return $local;
        }
    }

    public function url(?string $path, string $preset = 'display'): ?string
    {
        if (! $path) return null;
        if (! $this->isCloudinary($path)) {
            return preg_match('#^https?://#', $path) || str_starts_with($path, '/') ? $path : Storage::disk('public')->url($path);
        }
        $asset = Cache::remember('cloudinary:asset:'.sha1($path), now()->addMinutes(10), fn () => CloudinaryMediaAsset::where('path', $path)->first());
        if (! $asset || $asset->status !== 'ready') return null;
        if (! $this->enabled($asset->resource_type) && $asset->local_path && Storage::disk('public')->exists($asset->local_path)) {
            return Storage::disk('public')->url($asset->local_path);
        }
        $cloud = parse_url((string) config('cloudinary_media.url'), PHP_URL_HOST);
        if (! $cloud || ! preg_match('/^[a-zA-Z0-9_-]+$/', $cloud)) return null;
        $transforms = $asset->resource_type === 'image'
            ? ($preset === 'thumb' ? 'c_limit,w_480,q_auto,f_auto' : 'c_limit,w_1280,q_auto,f_auto')
            : ($preset === 'poster' ? 'so_1,c_limit,w_480,q_auto' : '');
        $version = $asset->version ? 'v'.$asset->version.'/' : '';
        return 'https://res.cloudinary.com/'.$cloud.'/'.$asset->resource_type.'/upload/'
            .($transforms ? $transforms.'/' : '').$version.$asset->public_id.'.'.($preset === 'poster' ? 'jpg' : $asset->format);
    }

    public function delete(?string $path): void
    {
        if (! $path) return;
        if (! $this->isCloudinary($path)) {
            Storage::disk('public')->delete($path);
            return;
        }
        $asset = CloudinaryMediaAsset::where('path', $path)->first();
        if (! $asset || $asset->status === 'deleted') return;
        $this->sendDestroy($asset->public_id, $asset->resource_type);
        if ($asset->local_path) Storage::disk('public')->delete($asset->local_path);
        $asset->update(['status' => 'deleted']);
        Cache::forget('cloudinary:asset:'.sha1($path));
    }

    public function deleteReplaced(?string $oldPath, ?string $newPath): void
    {
        if (! $this->isCloudinary($oldPath) || $oldPath === $newPath) return;
        try {
            $this->delete($oldPath);
        } catch (Throwable $e) {
            Log::warning('cloudinary_replaced_asset_delete_failed', ['path' => $oldPath, 'reason' => $e->getMessage()]);
        }
    }

    public function deleteUnreferencedEventPreview(?string $path): void
    {
        if (! $this->isCloudinary($path) || \App\Models\EventsImges::where('preview_url', $path)->exists()) return;
        $this->deleteReplaced($path, null);
    }

    public function usage(): array
    {
        return Cache::remember('cloudinary:usage', now()->addHours(6), function () {
            $data = $this->fetchUsage();
            if (! isset($data['credits']['usage'], $data['credits']['limit'])) {
                throw new RuntimeException('Cloudinary credit usage was omitted from the API response.');
            }
            $used = (float) $data['credits']['usage'];
            $limit = (float) $data['credits']['limit'];
            return ['used' => $used, 'limit' => $limit, 'percent' => $limit > 0 ? round(100 * $used / $limit, 1) : 100];
        });
    }

    private function assertBudget(int $bytes): void
    {
        $monthly = (int) CloudinaryMediaAsset::where('created_at', '>=', now()->startOfMonth())->sum('bytes');
        if ($monthly + $bytes > (int) config('cloudinary_media.max_monthly_upload_mb') * 1048576) {
            throw new RuntimeException('Cloudinary monthly upload budget reached.');
        }
        $usage = $this->usage();
        if ($usage['percent'] >= (int) config('cloudinary_media.quota_stop_percent')) {
            throw new RuntimeException('Cloudinary credit safety limit reached.');
        }
        if ($usage['percent'] >= (int) config('cloudinary_media.quota_warning_percent')) {
            Log::warning('cloudinary_credit_warning', $usage);
        }
    }

    protected function client(): Cloudinary
    {
        $url = config('cloudinary_media.url');
        if (! $url) throw new RuntimeException('Cloudinary credentials are unavailable.');
        return new Cloudinary($url);
    }

    protected function sendUpload(string $file, array $options): array
    {
        return $this->client()->uploadApi()->upload($file, $options)->getArrayCopy();
    }

    protected function sendDestroy(string $publicId, string $type): void
    {
        $this->client()->uploadApi()->destroy($publicId, ['resource_type' => $type, 'invalidate' => true]);
    }

    protected function fetchUsage(): array
    {
        return $this->client()->adminApi()->usage()->getArrayCopy();
    }
}
