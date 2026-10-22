<?php

namespace Tests\Feature\Security;

use App\Jobs\ProcessEventVideoJob;
use App\Models\Events;
use App\Models\EventsImges;
use App\Rules\SafeMedia;
use App\Services\EventTagCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\Support\MediaFixture;
use Tests\TestCase;

class UploadProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_inspection_rejects_executables_svg_malformed_media_and_pixel_bombs(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $huge = substr_replace($png, pack('NN', 100000, 100000), 16, 8);
        foreach ([['photo.jpg', '<?php echo "fixture";'], ['photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'], ['photo.png', 'broken'], ['clip.mp4', 'not-a-video'], ['huge.png', $huge]] as [$name, $bytes]) {
            $validator = Validator::make(['file' => UploadedFile::fake()->createWithContent($name, $bytes)], ['file' => [new SafeMedia]]);
            $this->assertTrue($validator->fails(), $name);
        }
        $this->assertTrue(Validator::make(['file' => UploadedFile::fake()->image('safe.jpg')], ['file' => [new SafeMedia]])->passes());
        $this->assertTrue(Validator::make(['file' => MediaFixture::video()], ['file' => [new SafeMedia]])->passes());
    }

    public function test_real_video_processing_keeps_original_and_watermarked_derivative_private(): void
    {
        Bus::fake();
        Storage::fake('public');
        $event = Events::create(['title' => 'Local video fixture', 'is_active' => true]);
        $file = MediaFixture::video();
        $source = $file->store('videos_temp', 'media');
        $hash = hash_file('sha256', Storage::disk('media')->path($source));
        (new ProcessEventVideoJob($event->id, $source, false, 'media'))->handle(app(EventTagCacheService::class));
        $media = EventsImges::where('event_id', $event->id)->sole();
        $this->assertSame('media', $media->original_disk);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('media')->path($media->full_url)));
        Storage::disk('media')->assertMissing($source);
        Storage::disk('media')->assertExists($media->preview_url);
        Storage::disk('public')->assertMissing($media->full_url);
        Storage::disk('public')->assertMissing($media->preview_url);
        $this->assertNotSame($media->full_url, $media->preview_url);
        $this->get(route('media.preview', ['media' => $media->id]))->assertOk();
    }
}
