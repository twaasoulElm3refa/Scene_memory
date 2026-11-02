<?php

namespace App\Services;

use App\Models\CloudinaryMediaAsset;
use App\Models\EventsImges;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IndependentMediaBackup
{
    public function configured(): bool
    {
        $name = config('cloudinary_media.backup_disk');
        $disk = $name ? config('filesystems.disks.'.$name) : null;
        if (! is_array($disk) || ($disk['driver'] ?? null) !== 's3' || empty($disk['bucket'])) return false;
        $endpoint = $disk['endpoint'] ?? null;
        if ($endpoint) {
            $host = parse_url($endpoint, PHP_URL_HOST);
            if (! $host || $host === 'localhost') return false;
            $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
            if (! $ips) return false;
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
            }
        }
        return true;
    }

    public function backUp(EventsImges $media, CloudinaryMediaAsset $asset): bool
    {
        if (! $this->configured()) {
            $asset->update(['backup_status' => 'pending']);
            return false;
        }
        return Cache::lock('media-original:'.$media->id, 300)->block(30, function () use ($media, $asset) {
            try {
                Log::info('media_backup_started', ['media_id' => $media->id]);
                $path = $this->privateOriginalPath($media);
                $local = Storage::disk('media');
                $hash = hash_file('sha256', $local->path($path));
                if ($hash !== $asset->sha256) throw new RuntimeException('Original checksum mismatch.');
                $diskName = (string) config('cloudinary_media.backup_disk');
                $backup = Storage::disk($diskName);
                if (! $asset->asset_id) throw new RuntimeException('Cloudinary asset identity is missing.');
                $key = trim((string) config('cloudinary_media.backup_prefix'), '/').'/'.$asset->id.'/'.$asset->asset_id.'.'.$asset->format;
                $stream = $local->readStream($path);
                try {
                    $backup->put($key, $stream, ['visibility' => 'private', 'ServerSideEncryption' => 'AES256']);
                } finally {
                    if (is_resource($stream)) fclose($stream);
                }
                $asset->update(['backup_disk' => $diskName, 'backup_key' => $key, 'backup_status' => 'pending']);
                if (! $this->verify($asset)) throw new RuntimeException('Backup integrity verification failed.');
                $asset->update(['backup_status' => 'verified', 'backup_sha256' => $hash, 'backup_verified_at' => now()]);
                Log::info('media_backup_verified', ['media_id' => $media->id, 'asset_id' => $asset->id]);
                return true;
            } catch (Throwable $e) {
                $asset->update(['backup_status' => 'failed', 'backup_verified_at' => null]);
                Log::warning('media_backup_failed', ['media_id' => $media->id, 'exception_category' => get_class($e)]);
                return false;
            }
        });
    }

    public function verify(CloudinaryMediaAsset $asset): bool
    {
        if (! $this->configured() || $asset->backup_disk !== config('cloudinary_media.backup_disk') || ! $asset->backup_key || ! $asset->sha256) return false;
        $disk = Storage::disk($asset->backup_disk);
        if (! $disk->exists($asset->backup_key) || (int) $disk->size($asset->backup_key) !== (int) $asset->bytes) return false;
        if ($disk->visibility($asset->backup_key) !== 'private') return false;
        $stream = $disk->readStream($asset->backup_key);
        if (! is_resource($stream)) return false;
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            return hash_equals($asset->sha256, hash_final($hash));
        } finally {
            fclose($stream);
        }
    }

    public function restorationCheck(CloudinaryMediaAsset $asset): bool
    {
        if (! $this->verify($asset)) return false;
        $path = 'recovery-checks/'.Str::uuid().'.bin';
        $target = Storage::disk('media');
        $stream = Storage::disk($asset->backup_disk)->readStream($asset->backup_key);
        try {
            $target->put($path, $stream);
            return hash_file('sha256', $target->path($path)) === $asset->sha256;
        } finally {
            if (is_resource($stream)) fclose($stream);
            $target->delete($path);
        }
    }

    private function privateOriginalPath(EventsImges $media): string
    {
        $path = $media->local_original_path;
        if (! is_string($path) || ! preg_match('#^events/full/[a-f0-9-]+\.[a-z0-9]+$#', $path)
            || ! Storage::disk('media')->exists($path)) throw new RuntimeException('Private original is unavailable.');
        return $path;
    }
}
