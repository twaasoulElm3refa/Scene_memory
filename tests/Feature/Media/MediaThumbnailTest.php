<?php

namespace Tests\Feature\Media;

use App\Jobs\GenerateMediaThumbnailJob;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use App\Services\MediaThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_active' => true]);
    }

    public function test_thumbnail_is_a_downscaled_copy_of_the_watermarked_preview_never_the_original(): void
    {
        // Preview red, original blue: the thumbnail must show the preview.
        $media = $this->media(['preview' => [2400, 1600, [220, 20, 20]], 'original' => [2400, 1600, [20, 20, 220]]]);
        $service = app(MediaThumbnailService::class);

        $this->assertTrue($service->generate($media));

        $file = $service->existing($media);
        $this->assertNotNull($file);
        [$width, $height, $type] = getimagesize($file);
        $this->assertSame([1280, 853], [$width, $height]);
        $this->assertSame(IMAGETYPE_WEBP, $type);
        $rgb = imagecolorat(imagecreatefromwebp($file), 640, 426);
        $this->assertGreaterThan(150, ($rgb >> 16) & 0xFF);
        $this->assertLessThan(80, $rgb & 0xFF);

        // Idempotent: the same source keeps the same file.
        $modified = filemtime($file);
        $this->assertTrue($service->generate($media));
        $this->assertSame($modified, filemtime($service->existing($media)));
        $this->assertCount(1, Storage::disk('media')->files('events/thumbs'));
    }

    public function test_small_images_are_never_upscaled(): void
    {
        $media = $this->media(['preview' => [640, 480, [10, 200, 10]]]);

        app(MediaThumbnailService::class)->generate($media);

        $this->assertSame([640, 480], array_slice(getimagesize(app(MediaThumbnailService::class)->existing($media)), 0, 2));
    }

    public function test_paid_media_without_a_preview_gets_no_thumbnail(): void
    {
        $media = $this->media(['original' => [1000, 800, [1, 2, 3]]]);

        $this->assertFalse(app(MediaThumbnailService::class)->generate($media));
        $this->assertSame([], Storage::disk('media')->allFiles('events/thumbs'));
        $this->get("/api/v1/media/{$media->id}/thumbnail")->assertNotFound();
    }

    public function test_free_media_without_a_preview_uses_the_already_public_original(): void
    {
        $media = $this->media(['original' => [900, 600, [9, 9, 9]]], ['price' => '0.00']);

        $this->assertTrue(app(MediaThumbnailService::class)->generate($media));
    }

    public function test_the_thumbnail_route_serves_the_preview_until_the_thumbnail_exists(): void
    {
        Queue::fake([GenerateMediaThumbnailJob::class]);
        $media = $this->media(['preview' => [1600, 1200, [200, 10, 10]]]);

        $first = $this->get("/api/v1/media/{$media->id}/thumbnail")->assertOk();
        $this->assertSame('image/jpeg', $first->headers->get('Content-Type'));
        Queue::assertPushed(GenerateMediaThumbnailJob::class, fn ($job) => $job->mediaId === $media->id);

        app(MediaThumbnailService::class)->generate($media);
        $second = $this->get("/api/v1/media/{$media->id}/thumbnail")->assertOk();
        $this->assertSame('image/webp', $second->headers->get('Content-Type'));
        $this->assertLessThan(filesize(Storage::disk('media')->path('events/preview/'.$media->id.'.jpg')), filesize($second->getFile()->getPathname()));
    }

    public function test_only_published_media_is_publicly_cacheable(): void
    {
        $published = $this->media(['preview' => [800, 600, [1, 1, 1]]]);
        foreach (['preview', 'thumbnail'] as $route) {
            $response = $this->get("/api/v1/media/{$published->id}/{$route}")->assertOk();
            $cache = $response->headers->get('Cache-Control');
            $this->assertStringContainsString('public', $cache);
            $this->assertStringContainsString('max-age=3600', $cache);
            $this->assertNotNull($response->headers->get('Last-Modified'));
            $this->get("/api/v1/media/{$published->id}/{$route}", ['If-Modified-Since' => $response->headers->get('Last-Modified')])->assertStatus(304);
        }

        $hidden = $this->media(['preview' => [800, 600, [1, 1, 1]]], ['is_active' => '0']);
        $this->get("/api/v1/media/{$hidden->id}/thumbnail")->assertNotFound();
        $signed = URL::temporarySignedRoute('media.thumbnail', now()->addMinutes(5), ['media' => $hidden->id]);
        $cache = $this->get($signed)->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);

        config(['media_security.public_max_age' => 0]);
        $this->assertStringContainsString('no-store', $this->get("/api/v1/media/{$published->id}/preview")->headers->get('Cache-Control'));
    }

    public function test_media_json_links_the_thumbnail_only_where_the_preview_is_visible(): void
    {
        $event = Events::create(['user_id' => $this->owner->id, 'title' => 'Thumbs', 'slug' => 'thumbs', 'is_active' => 1]);
        $visible = $this->media(['preview' => [800, 600, [1, 1, 1]]], [], $event);
        $noPreview = $this->media([], [], $event);
        $video = $this->media(['preview' => [800, 600, [1, 1, 1]]], ['type' => 'video'], $event);

        $images = collect($this->getJson('/api/v1/events/thumbs/single/get')->assertOk()->json('data.images'))->keyBy('id');
        $this->assertSame(route('media.thumbnail', ['media' => $visible->id]), $images[$visible->id]['thumbnail_url']);
        $this->assertNull($images[$noPreview->id]['thumbnail_url']);
        $this->assertNull($images[$video->id]['thumbnail_url']);

        // Unpublished media: like preview_url, others get a plain link that the endpoint
        // refuses; only the owner gets a working signed URL.
        $hidden = $this->media(['preview' => [800, 600, [1, 1, 1]]], ['is_active' => '0'], $event);
        $guestUrl = $hidden->fresh()->toArray()['thumbnail_url'];
        $this->assertStringNotContainsString('signature=', $guestUrl);
        $this->get($guestUrl)->assertNotFound();
        Sanctum::actingAs($this->owner);
        $this->assertStringContainsString('signature=', $hidden->fresh()->toArray()['thumbnail_url']);
    }

    public function test_backfill_generates_missing_thumbnails_once(): void
    {
        $this->media(['preview' => [900, 700, [5, 5, 5]]]);
        $this->media(['preview' => [900, 700, [6, 6, 6]]]);
        $this->media(['original' => [900, 700, [7, 7, 7]]]);

        Artisan::call('media:thumbnails', ['--dry-run' => true]);
        $this->assertStringContainsString('would generate 2', Artisan::output());
        $this->assertSame([], Storage::disk('media')->allFiles('events/thumbs'));

        Artisan::call('media:thumbnails');
        $this->assertStringContainsString('generated 2, already present 0, skipped 1', Artisan::output());
        Artisan::call('media:thumbnails');
        $this->assertStringContainsString('generated 0, already present 2, skipped 1', Artisan::output());
    }

    /**
     * @param  array{preview?: array{0:int,1:int,2:array{0:int,1:int,2:int}}, original?: array{0:int,1:int,2:array{0:int,1:int,2:int}}}  $files
     */
    private function media(array $files, array $attributes = [], ?Events $event = null): EventsImges
    {
        $event ??= Events::create(['user_id' => $this->owner->id, 'title' => 'Thumb event', 'slug' => 'thumb-'.uniqid(), 'is_active' => 1]);
        $media = EventsImges::create(array_merge(['event_id' => $event->id, 'type' => 'image', 'is_active' => '1', 'price' => '5.00',
            'original_disk' => 'media', 'preview_disk' => 'media'], $attributes));
        // Every item has an original on disk, as in production; the preview is optional.
        $files += ['original' => [1200, 900, [20, 20, 220]]];
        $paths = [];
        foreach (['preview' => 'events/preview/', 'original' => 'events/full/'] as $kind => $directory) {
            if (isset($files[$kind])) {
                [$width, $height, $color] = $files[$kind];
                $image = imagecreatetruecolor($width, $height);
                imagefill($image, 0, 0, imagecolorallocate($image, ...$color));
                ob_start();
                imagejpeg($image, null, 90);
                Storage::disk('media')->put($paths[$kind] = $directory.$media->id.'.jpg', ob_get_clean());
            }
        }
        $media->update(['preview_url' => $paths['preview'] ?? null, 'full_url' => $paths['original']]);

        return $media->fresh();
    }
}
