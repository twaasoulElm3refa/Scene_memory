<?php

namespace App\Services;

use App\Models\EventsImges;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class VideoFrameExtractor
{
    /**
     * Extract a small set of representative JPEG frames for vision analysis.
     *
     * @return array<int, string> Paths on the public disk.
     */
    public function extract(EventsImges $video, int $limit): array
    {
        $limit = max(0, min($limit, (int) config('ai_tags.video_frames_limit', 5)));
        $videoPath = ltrim((string) $video->full_url, '/');
        $disk = Storage::disk('public');

        if ($limit === 0 || $videoPath === '' || ! $disk->exists($videoPath)) {
            return [];
        }

        $absoluteVideoPath = $disk->path($videoPath);
        $directory = 'ai-video-frames/event-'.$video->event_id.'/media-'.$video->id.'-'.Str::uuid();
        $disk->makeDirectory($directory);

        try {
            $duration = $this->durationInSeconds($absoluteVideoPath);
            $timestamps = $this->representativeTimestamps($duration, $limit);
            $frames = [];

            foreach ($timestamps as $index => $timestamp) {
                $relativePath = $directory.'/frame-'.($index + 1).'.jpg';
                $process = new Process([
                    (string) config('services.ffmpeg.binary', 'ffmpeg'),
                    '-hide_banner',
                    '-loglevel',
                    'error',
                    '-y',
                    '-ss',
                    number_format($timestamp, 3, '.', ''),
                    '-i',
                    $absoluteVideoPath,
                    '-frames:v',
                    '1',
                    '-vf',
                    'scale=1280:-2:force_original_aspect_ratio=decrease',
                    '-q:v',
                    '3',
                    $disk->path($relativePath),
                ]);
                $process->setTimeout(45);
                $process->run();

                if ($process->isSuccessful() && $disk->exists($relativePath) && $disk->size($relativePath) > 0) {
                    $frames[] = $relativePath;

                    continue;
                }

                $disk->delete($relativePath);
                Log::warning('video_ai_frame_extraction_failed', [
                    'event_id' => $video->event_id,
                    'media_id' => $video->id,
                    'timestamp' => $timestamp,
                    'exit_code' => $process->getExitCode(),
                    'error' => mb_substr(trim($process->getErrorOutput()), 0, 1000),
                ]);
            }

            return $frames;
        } catch (Throwable $exception) {
            Log::error('video_ai_frame_extraction_exception', [
                'event_id' => $video->event_id,
                'media_id' => $video->id,
                'message' => $exception->getMessage(),
            ]);

            $disk->deleteDirectory($directory);

            return [];
        }
    }

    /**
     * @param  array<int, string>  $framePaths
     */
    public function cleanup(array $framePaths): void
    {
        $disk = Storage::disk('public');
        $directories = [];

        foreach ($framePaths as $path) {
            $path = ltrim((string) $path, '/');

            if ($path === '') {
                continue;
            }

            $directories[] = dirname($path);
            $disk->delete($path);
        }

        foreach (array_unique($directories) as $directory) {
            $disk->deleteDirectory($directory);
        }
    }

    private function durationInSeconds(string $absoluteVideoPath): ?float
    {
        $process = new Process([
            (string) config('services.ffmpeg.ffprobe_binary', 'ffprobe'),
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $absoluteVideoPath,
        ]);
        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $duration = filter_var(trim($process->getOutput()), FILTER_VALIDATE_FLOAT);

        return $duration !== false && $duration > 0 ? (float) $duration : null;
    }

    /**
     * @return array<int, float>
     */
    private function representativeTimestamps(?float $duration, int $limit): array
    {
        if ($duration === null) {
            return [0.5];
        }

        $ratios = [0.05, 0.25, 0.5, 0.75, 0.95];

        return collect($ratios)
            ->take($limit)
            ->map(fn (float $ratio) => max(0.0, min($duration - 0.05, $duration * $ratio)))
            ->map(fn (float $timestamp) => round($timestamp, 3))
            ->unique()
            ->values()
            ->all();
    }
}
