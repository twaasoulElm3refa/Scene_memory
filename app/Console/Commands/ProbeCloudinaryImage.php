<?php

namespace App\Console\Commands;

use App\Models\CloudinaryMediaAsset;
use App\Services\UnifiedMediaStorage;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ProbeCloudinaryImage extends Command
{
    protected $signature = 'media:cloudinary-probe {--confirm : Upload and then remove one synthetic image} {--authenticated : Test protected original delivery without a database media record}';

    protected $description = 'Verify the configured Cloudinary image upload, database metadata, Media Library and CDN without customer media';

    public function handle(UnifiedMediaStorage $storage): int
    {
        if (! $this->option('confirm')) {
            $this->error('No upload made. Pass --confirm to run the synthetic probe.');
            return self::INVALID;
        }
        if (! $storage->enabled('image')) {
            $this->error('image_storage=disabled');
            return self::FAILURE;
        }
        if ($this->option('authenticated')) return $this->probeAuthenticated();

        $file = sys_get_temp_dir().'/scemory-cloud-probe-'.Str::uuid().'.png';
        $path = null;
        $result = self::FAILURE;
        try {
            $png = imagecreatetruecolor(32, 32);
            imagefill($png, 0, 0, imagecolorallocate($png, 70, 120, 180));
            imagepng($png, $file);
            imagedestroy($png);

            $path = $storage->upload($file, 'image', 'diagnostics');
            $asset = CloudinaryMediaAsset::where('path', $path)->firstOrFail();
            $this->line('upload=successful');
            $this->line('metadata_persisted='.($asset->resource_type === 'image' && $asset->bytes > 0 ? 'true' : 'false'));

            $found = (new Cloudinary((string) config('cloudinary_media.url')))->adminApi()->asset($asset->public_id)->getArrayCopy();
            $this->line('media_library='.($found['public_id'] === $asset->public_id ? 'verified' : 'mismatch'));

            $response = Http::timeout(15)->get((string) $storage->url($path));
            $this->line('cdn_delivery='.$response->status());
            $result = $found['public_id'] === $asset->public_id && $response->successful() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error('probe_failed='.class_basename($e));
        } finally {
            if ($path) {
                try {
                    $storage->delete($path);
                    $this->line('synthetic_asset_cleanup=successful');
                } catch (Throwable $e) {
                    $this->error('synthetic_asset_cleanup=failed('.class_basename($e).')');
                    $result = self::FAILURE;
                }
            }
            if (is_file($file)) unlink($file);
        }
        return $result;
    }

    private function probeAuthenticated(): int
    {
        $file = sys_get_temp_dir().'/scemory-auth-probe-'.Str::uuid().'.png';
        $publicId = 'scemory/diagnostics-auth/'.Str::uuid();
        $uploaded = false;
        $result = self::FAILURE;
        $cloud = new Cloudinary((string) config('cloudinary_media.url'));
        try {
            $png = imagecreatetruecolor(32, 32);
            imagefill($png, 0, 0, imagecolorallocate($png, 70, 120, 180));
            imagepng($png, $file);
            imagedestroy($png);
            $response = $cloud->uploadApi()->upload($file, [
                'resource_type' => 'image', 'type' => 'authenticated',
                'public_id' => $publicId, 'overwrite' => false,
            ])->getArrayCopy();
            $uploaded = true;
            $found = $cloud->adminApi()->asset($publicId, [
                'resource_type' => 'image', 'type' => 'authenticated',
            ])->getArrayCopy();
            $metadataOk = ($response['type'] ?? null) === 'authenticated'
                && ($found['type'] ?? null) === 'authenticated'
                && ($found['asset_id'] ?? null) === ($response['asset_id'] ?? null)
                && (int) ($found['bytes'] ?? -1) === filesize($file);
            $this->line('authenticated_metadata='.($metadataOk ? 'verified' : 'mismatch'));

            $cloudName = $cloud->configuration->cloud->cloudName;
            $unsignedUrl = 'https://res.cloudinary.com/'.$cloudName.'/image/authenticated/'.$publicId.'.png';
            $unsigned = Http::timeout(20)->get($unsignedUrl);
            $this->line('unsigned_delivery_status='.$unsigned->status());
            $transformed = Http::timeout(20)->get('https://res.cloudinary.com/'.$cloudName.'/image/authenticated/c_limit,w_64/'.$publicId.'.png');
            $this->line('unsigned_transformation_status='.$transformed->status());
            $privateUrl = $cloud->uploadApi()->privateDownloadUrl($publicId, 'png', [
                'type' => 'authenticated', 'resource_type' => 'image', 'expires_at' => time() + 120,
            ]);
            $authorized = Http::timeout(30)->get($privateUrl);
            $retrievable = $authorized->successful() && hash('sha256', $authorized->body()) === hash_file('sha256', $file);
            $this->line('authorized_retrieval='.($retrievable ? 'verified' : 'failed'));
            $expiredUrl = $cloud->uploadApi()->privateDownloadUrl($publicId, 'png', [
                'type' => 'authenticated', 'resource_type' => 'image', 'expires_at' => time() - 60,
            ]);
            $expired = Http::timeout(20)->get($expiredUrl);
            $this->line('expired_delivery_status='.$expired->status());
            $result = $metadataOk && ! $unsigned->successful() && ! $transformed->successful()
                && $retrievable && ! $expired->successful() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error('authenticated_probe_failed='.class_basename($e));
        } finally {
            if ($uploaded) {
                try {
                    $cloud->uploadApi()->destroy($publicId, [
                        'resource_type' => 'image', 'type' => 'authenticated', 'invalidate' => true,
                    ]);
                    $this->line('synthetic_asset_cleanup=successful');
                } catch (Throwable $e) {
                    $this->error('synthetic_asset_cleanup=failed('.class_basename($e).')');
                    $result = self::FAILURE;
                }
            }
            if (is_file($file)) unlink($file);
        }
        return $result;
    }
}
