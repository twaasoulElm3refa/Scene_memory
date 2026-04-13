<?php

namespace App\Jobs;

use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessEventVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1200;

    public $eventId;
    public $filePath;
    public string $debugId;

    public function __construct($eventId, $filePath)
    {
        $this->eventId  = $eventId;
        $this->filePath = $filePath;
        $this->debugId  = (string) Str::uuid();
    }

    public function handle(): void
    {
        $event = Events::find($this->eventId);

        if (!$event) {
            Storage::disk('public')->delete($this->filePath);
            return;
        }

        $disk = Storage::disk('public');
        $tempAbsPath = $disk->path($this->filePath);

        if (!file_exists($tempAbsPath)) {
            return;
        }

        try {
            $finalFilename = 'videos/' . basename($this->filePath);
            $finalAbsPath  = $disk->path($finalFilename);

            if (!is_dir(dirname($finalAbsPath))) {
                mkdir(dirname($finalAbsPath), 0775, true);
            }

            $renameResult = @rename($tempAbsPath, $finalAbsPath);

            if (!$renameResult) {
                $disk->move($this->filePath, $finalFilename);
            }

            if (!file_exists($finalAbsPath)) {
                throw new \RuntimeException('Final video file does not exist after move/rename.');
            }

            $previewWatermarkedPath = $this->makeWatermarkedPreviewVideo($finalFilename, $this->debugId);

            if (!$previewWatermarkedPath) {
                throw new \RuntimeException('Watermarked preview video generation returned null.');
            }

            eventsImges::create([
                'event_id'    => $this->eventId,
                'preview_url' => $previewWatermarkedPath,
                'full_url'    => $finalFilename,
                'price'       => 15,
                'is_active'   => 1,
            ]);

            $this->clearEventsCache($event->slug);

        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function makeWatermarkedPreviewVideo(string $videoPath, string $debugId): ?string
    {
        $disk = Storage::disk('public');
        $inputPath = $disk->path($videoPath);

        Log::info('makeWatermarkedPreviewVideo: START', [
            'debug_id'       => $debugId,
            'video_relative' => $videoPath,
            'video_absolute' => $inputPath,
            'video_exists'   => file_exists($inputPath),
            'video_size'     => file_exists($inputPath) ? filesize($inputPath) : null,
            'video_mime'     => file_exists($inputPath) ? @mime_content_type($inputPath) : null,
        ]);

        if (!file_exists($inputPath)) {
            Log::error('makeWatermarkedPreviewVideo: input video not found', [
                'debug_id'   => $debugId,
                'input_path' => $inputPath,
            ]);
            return null;
        }

        $watermarkPath = public_path('images/watermark.png');

        Log::info('makeWatermarkedPreviewVideo: watermark path check', [
            'debug_id'         => $debugId,
            'watermark_path'   => $watermarkPath,
            'watermark_exists' => file_exists($watermarkPath),
        ]);

        if (!file_exists($watermarkPath)) {
            Log::error('makeWatermarkedPreviewVideo: watermark not found', [
                'debug_id'       => $debugId,
                'watermark_path' => $watermarkPath,
            ]);
            return null;
        }

        $wmInfo = @getimagesize($watermarkPath);

        Log::info('makeWatermarkedPreviewVideo: watermark file info', [
            'debug_id'         => $debugId,
            'watermark_size'   => @filesize($watermarkPath),
            'watermark_width'  => $wmInfo[0] ?? null,
            'watermark_height' => $wmInfo[1] ?? null,
            'watermark_mime'   => $wmInfo['mime'] ?? null,
        ]);

        $ffmpegBin = env('FFMPEG_BIN', 'ffmpeg');

        $ffmpegCheckOutput = [];
        $ffmpegCheckCode = null;
        @exec($ffmpegBin . ' -version 2>&1', $ffmpegCheckOutput, $ffmpegCheckCode);

        Log::info('makeWatermarkedPreviewVideo: ffmpeg availability', [
            'debug_id'    => $debugId,
            'ffmpeg_bin'  => $ffmpegBin,
            'return_code' => $ffmpegCheckCode,
            'first_line'  => $ffmpegCheckOutput[0] ?? null,
        ]);

        if ($ffmpegCheckCode !== 0) {
            Log::error('makeWatermarkedPreviewVideo: ffmpeg not available', [
                'debug_id' => $debugId,
                'output'   => implode("\n", $ffmpegCheckOutput),
            ]);
            return null;
        }

        $baseName = pathinfo(basename($videoPath), PATHINFO_FILENAME);

        $outputRelative = 'videos/preview_wm_' . $baseName . '_' . time() . '.mp4';
        $outputPath = $disk->path($outputRelative);

        Log::info('makeWatermarkedPreviewVideo: output target prepared', [
            'debug_id'        => $debugId,
            'output_relative' => $outputRelative,
            'output_absolute' => $outputPath,
        ]);

        // واترمارك أكبر جدًا ومتمركزة على الفيديو
        $filter = "[1:v]format=rgba,colorchannelmixer=aa=0.90[wm];" .
                  "[wm][0:v]scale2ref=w=main_w*1.90:h=ow/mdar[wm_s][base];" .
                  "[base][wm_s]overlay=(main_w-overlay_w)/2:(main_h-overlay_h)/2:format=auto[vout]";

        Log::info('makeWatermarkedPreviewVideo: filter prepared', [
            'debug_id' => $debugId,
            'filter'   => $filter,
        ]);

        $cmd = sprintf(
            '%s -y -i %s -i %s -filter_complex %s -map %s -map 0:a? -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart -shortest %s 2>&1',
            escapeshellcmd($ffmpegBin),
            escapeshellarg($inputPath),
            escapeshellarg($watermarkPath),
            escapeshellarg($filter),
            escapeshellarg('[vout]'),
            escapeshellarg($outputPath)
        );

        Log::info('makeWatermarkedPreviewVideo: executing ffmpeg', [
            'debug_id' => $debugId,
            'cmd'      => $cmd,
        ]);

        $output = [];
        $returnCode = null;
        @exec($cmd, $output, $returnCode);

        Log::info('makeWatermarkedPreviewVideo: ffmpeg finished', [
            'debug_id'           => $debugId,
            'return_code'        => $returnCode,
            'output_file_exists' => file_exists($outputPath),
            'output_file_size'   => file_exists($outputPath) ? filesize($outputPath) : null,
            'output'             => implode("\n", $output),
        ]);

        if ($returnCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error('makeWatermarkedPreviewVideo: ffmpeg failed', [
                'debug_id'    => $debugId,
                'return_code' => $returnCode,
                'output'      => implode("\n", $output),
            ]);

            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return null;
        }

        $debugFrameRelative = 'videos/debug_frame_' . $baseName . '_' . time() . '.jpg';
        $debugFramePath = $disk->path($debugFrameRelative);

        $frameCmd = sprintf(
            '%s -y -i %s -map 0:v:0 -ss 00:00:01 -frames:v 1 -update 1 %s 2>&1',
            escapeshellcmd($ffmpegBin),
            escapeshellarg($outputPath),
            escapeshellarg($debugFramePath)
        );

        Log::info('makeWatermarkedPreviewVideo: extracting debug frame', [
            'debug_id'  => $debugId,
            'frame_cmd' => $frameCmd,
        ]);

        $frameOutput = [];
        $frameReturn = null;
        @exec($frameCmd, $frameOutput, $frameReturn);

        Log::info('makeWatermarkedPreviewVideo: debug frame generated', [
            'debug_id'          => $debugId,
            'frame_return_code' => $frameReturn,
            'frame_exists'      => file_exists($debugFramePath),
            'frame_size'        => file_exists($debugFramePath) ? filesize($debugFramePath) : null,
            'frame_path'        => $debugFrameRelative,
            'frame_output'      => implode("\n", $frameOutput),
        ]);

        Log::info('makeWatermarkedPreviewVideo: END SUCCESS', [
            'debug_id'         => $debugId,
            'preview_relative' => $outputRelative,
            'preview_absolute' => $outputPath,
        ]);

        return $outputRelative;
    }

    public function failed(\Throwable $exception): void
    {
        Storage::disk('public')->delete($this->filePath);
    }

    private function clearEventsCache(?string $slug = null): void
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

        if ($slug) {
            Cache::forget("events_single_{$slug}");

            foreach ($locales as $locale) {
                Cache::forget("events_single_{$slug}_{$locale}");
            }

            try {
                Cache::tags(['events'])->forget(
                    'event_' . strtolower(trim($slug)) . '_' . app()->getLocale()
                );
            } catch (\Throwable $e) {
                //
            }
        }

        Cache::flush();
    }
}
