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
    protected $signature = 'media:cloudinary-probe {--confirm : Upload and then remove one synthetic image}';

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
}
