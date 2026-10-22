<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\EventsImges;
use App\Services\EventTagCacheService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProcessEventVideoJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public $eventId;

    public string $sourceDisk = 'public';

    public ?float $manualPrice = null;

    public $filePath;

    public string $debugId;

    public bool $dispatchAiAfterProcessing;

    public function __construct($eventId, $filePath, bool $dispatchAiAfterProcessing = false, string $sourceDisk = 'public', ?float $manualPrice = null)
    {
        $this->eventId = $eventId;
        $this->sourceDisk = $sourceDisk;
        $this->manualPrice = $manualPrice;
        $this->filePath = $filePath;
        $this->debugId = (string) Str::uuid();
        $this->dispatchAiAfterProcessing = $dispatchAiAfterProcessing;
    }

    public function handle(EventTagCacheService $cache): void
    {
        $event = Events::find($this->eventId);

        if (! $event) {
            Storage::disk($this->sourceDisk)->delete($this->filePath);

            return;
        }

        $disk = Storage::disk('media');
        $tempAbsPath = Storage::disk($this->sourceDisk)->path($this->filePath);
        $finalFilename = 'videos/'.hash('sha256', $this->eventId.'|'.$this->sourceDisk.'|'.$this->filePath).'.'.pathinfo($this->filePath, PATHINFO_EXTENSION);
        $finalAbsPath = $disk->path($finalFilename);

        $existingMedia = EventsImges::query()
            ->where('event_id', $this->eventId)
            ->where('type', 'video')
            ->where('full_url', $finalFilename)
            ->first();

        if (
            $existingMedia &&
            $existingMedia->preview_url &&
            $disk->exists($existingMedia->preview_url)
        ) {
            $cache->invalidateEvent((int) $event->id, [(int) $existingMedia->id]);

            if ($this->dispatchAiAfterProcessing) {
                GenerateEventAiTagsJob::dispatch((int) $event->id);
            }

            return;
        }

        try {
            if (! is_dir(dirname($finalAbsPath))) {
                mkdir(dirname($finalAbsPath), 0775, true);
            }

            if (! file_exists($finalAbsPath) && file_exists($tempAbsPath)) {
                $disk->put($finalFilename, fopen($tempAbsPath, 'rb'));
            }

            if (! file_exists($finalAbsPath)) {
                throw new \RuntimeException('Video source is unavailable for watermark processing.');
            }

            $previewWatermarkedPath = $this->makeWatermarkedPreviewVideo($finalFilename, $this->debugId);

            if (! $previewWatermarkedPath) {
                throw new \RuntimeException('Watermarked preview video generation failed.');
            }

            $media = EventsImges::updateOrCreate([
                'event_id' => $this->eventId,
                'type' => 'video',
                'full_url' => $finalFilename,
            ], [
                'preview_url' => $previewWatermarkedPath,
                'price' => $this->manualPrice ?? 15,
                'original_disk' => 'media', 'preview_disk' => 'media',
                'is_active' => 1,
            ]);
            app(\App\Services\MediaTierService::class)->assignTierToMedia($media);

            $cache->invalidateEvent((int) $event->id, [(int) $media->id]);
            Storage::disk($this->sourceDisk)->delete($this->filePath);

            if ($this->dispatchAiAfterProcessing) {
                GenerateEventAiTagsJob::dispatch((int) $event->id);
            }

        } catch (\Throwable $exception) {
            Log::error('ProcessEventVideoJob: failed', [
                'event_id' => $this->eventId,
                'source_path' => $this->filePath,
                'final_path' => $finalFilename,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function makeWatermarkedPreviewVideo(string $videoPath, string $debugId): ?string
    {
        $disk = Storage::disk('media');
        $inputPath = $disk->path($videoPath);

        Log::info('makeWatermarkedPreviewVideo: START', [
            'debug_id' => $debugId,
            'video_relative' => $videoPath,
            'video_absolute' => $inputPath,
            'video_exists' => file_exists($inputPath),
            'video_size' => file_exists($inputPath) ? filesize($inputPath) : null,
            'video_mime' => file_exists($inputPath) ? @mime_content_type($inputPath) : null,
        ]);

        if (! file_exists($inputPath)) {
            Log::error('makeWatermarkedPreviewVideo: input video not found', [
                'debug_id' => $debugId,
                'input_path' => $inputPath,
            ]);

            return null;
        }

        $watermarkPath = public_path('images/watermark.png');

        Log::info('makeWatermarkedPreviewVideo: watermark path check', [
            'debug_id' => $debugId,
            'watermark_path' => $watermarkPath,
            'watermark_exists' => file_exists($watermarkPath),
        ]);

        if (! file_exists($watermarkPath)) {
            Log::error('makeWatermarkedPreviewVideo: watermark not found', [
                'debug_id' => $debugId,
                'watermark_path' => $watermarkPath,
            ]);

            return null;
        }

        $wmInfo = @getimagesize($watermarkPath);

        Log::info('makeWatermarkedPreviewVideo: watermark file info', [
            'debug_id' => $debugId,
            'watermark_size' => @filesize($watermarkPath),
            'watermark_width' => $wmInfo[0] ?? null,
            'watermark_height' => $wmInfo[1] ?? null,
            'watermark_mime' => $wmInfo['mime'] ?? null,
        ]);

        $ffmpegBin = (string) config('services.ffmpeg.binary', 'ffmpeg');

        $ffmpegCheckOutput = [];
        $ffmpegCheckCode = null;
        @exec($ffmpegBin.' -version 2>&1', $ffmpegCheckOutput, $ffmpegCheckCode);

        Log::info('makeWatermarkedPreviewVideo: ffmpeg availability', [
            'debug_id' => $debugId,
            'ffmpeg_bin' => $ffmpegBin,
            'return_code' => $ffmpegCheckCode,
            'first_line' => $ffmpegCheckOutput[0] ?? null,
        ]);

        if ($ffmpegCheckCode !== 0) {
            Log::error('makeWatermarkedPreviewVideo: ffmpeg not available', [
                'debug_id' => $debugId,
                'output' => implode("\n", $ffmpegCheckOutput),
            ]);

            return null;
        }

        $baseName = pathinfo(basename($videoPath), PATHINFO_FILENAME);

        $outputRelative = 'videos/preview_wm_'.$baseName.'_'.time().'.mp4';
        $outputPath = $disk->path($outputRelative);

        Log::info('makeWatermarkedPreviewVideo: output target prepared', [
            'debug_id' => $debugId,
            'output_relative' => $outputRelative,
            'output_absolute' => $outputPath,
        ]);

        // واترمارك أكبر جدًا ومتمركزة على الفيديو
        $filter = '[1:v]format=rgba,colorchannelmixer=aa=0.90[wm];'.
                  '[wm][0:v]scale2ref=w=main_w*1.90:h=ow/mdar[wm_s][base];'.
                  '[base][wm_s]overlay=(main_w-overlay_w)/2:(main_h-overlay_h)/2:format=auto[vout]';

        Log::info('makeWatermarkedPreviewVideo: filter prepared', [
            'debug_id' => $debugId,
            'filter' => $filter,
        ]);

        $cmd = sprintf(
            '%s -nostdin -threads 2 -filter_complex_threads 2 -protocol_whitelist file,pipe -y -i %s -i %s -filter_complex %s -map %s -map 0:a? -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart -shortest %s 2>&1',
            escapeshellcmd($ffmpegBin),
            escapeshellarg($inputPath),
            escapeshellarg($watermarkPath),
            escapeshellarg($filter),
            escapeshellarg('[vout]'),
            escapeshellarg($outputPath)
        );

        Log::info('makeWatermarkedPreviewVideo: executing ffmpeg', [
            'debug_id' => $debugId,
            'cmd' => $cmd,
        ]);

        $output = [];
        $returnCode = null;
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout((int) config('media_security.video_process_timeout', 300));
        $process->run();
        $returnCode = $process->getExitCode();
        $output = [mb_substr($process->getOutput().$process->getErrorOutput(), 0, 2000)];

        Log::info('makeWatermarkedPreviewVideo: ffmpeg finished', [
            'debug_id' => $debugId,
            'return_code' => $returnCode,
            'output_file_exists' => file_exists($outputPath),
            'output_file_size' => file_exists($outputPath) ? filesize($outputPath) : null,
            'output' => implode("\n", $output),
        ]);

        if ($returnCode !== 0 || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error('makeWatermarkedPreviewVideo: ffmpeg failed', [
                'debug_id' => $debugId,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);

            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return null;
        }

        return $outputRelative;
    }

    public function failed(\Throwable $exception): void
    {
        Storage::disk($this->sourceDisk)->delete($this->filePath);
    }
}
