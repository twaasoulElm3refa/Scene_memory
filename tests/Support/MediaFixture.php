<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class MediaFixture
{
    public static function video(): UploadedFile
    {
        static $bytes;
        if ($bytes === null) {
            $path = tempnam(sys_get_temp_dir(), 'scemory-video-').'.mp4';
            try {
                $process = new Process(['ffmpeg', '-nostdin', '-y', '-f', 'lavfi', '-i', 'color=c=blue:s=32x32:d=1', '-threads', '1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $path]);
                $process->setTimeout(20);
                $process->mustRun();
                $bytes = file_get_contents($path);
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
                if (is_file(substr($path, 0, -4))) {
                    unlink(substr($path, 0, -4));
                }
            }
        }

        return UploadedFile::fake()->createWithContent('event.mp4', $bytes);
    }
}
