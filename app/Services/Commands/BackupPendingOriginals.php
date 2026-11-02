<?php

namespace App\Services\Commands;

use App\Models\CloudinaryMediaAsset;
use App\Services\IndependentMediaBackup;
use Illuminate\Console\Command;

class BackupPendingOriginals extends Command
{
    protected $signature = 'media:backup-originals {--media= : Process one media ID} {--limit=25 : Maximum pending originals} {--restore-check : Read and restore one verified backup to a temporary private file}';

    protected $description = 'Retry independent backups of authenticated originals, or test restoration of one';

    public function handle(IndependentMediaBackup $backup): int
    {
        if (! $backup->configured()) {
            $this->error('independent_backup=unconfigured');
            return self::FAILURE;
        }
        $mediaId = $this->option('media') ? (int) $this->option('media') : null;
        if ($this->option('restore-check')) {
            if (! $mediaId) return self::INVALID;
            $asset = CloudinaryMediaAsset::where('event_media_id', $mediaId)->where('delivery_type', 'authenticated')->first();
            if (! $asset || $asset->backup_status !== 'verified') return self::FAILURE;
            $ok = $backup->restorationCheck($asset);
            $this->line('restoration_check='.($ok ? 'verified' : 'failed'));
            return $ok ? self::SUCCESS : self::FAILURE;
        }
        $query = CloudinaryMediaAsset::where('delivery_type', 'authenticated')->where('resource_type', 'image')
            ->where('status', 'ready')->whereNotNull('event_media_id')->where('backup_status', '!=', 'verified');
        if ($mediaId) $query->where('event_media_id', $mediaId);
        $assets = $query->orderBy('id')->limit(min(100, max(1, (int) $this->option('limit'))))->get();
        $verified = 0;
        foreach ($assets as $asset) {
            $media = $asset->eventMedia;
            if ($media && $media->type === 'image' && $backup->backUp($media, $asset)) $verified++;
        }
        $this->line('backup_attempted='.$assets->count().' verified='.$verified);
        return $verified === $assets->count() ? self::SUCCESS : self::FAILURE;
    }
}
