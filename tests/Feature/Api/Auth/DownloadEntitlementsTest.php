<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DownloadEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_are_unique_and_ordered_by_latest_entitlement(): void
    {
        $this->allowDuplicateEntitlementsForRegressionCoverage();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Events::create(['user_id' => $otherUser->id, 'is_active' => true]);
        $oldThenNewest = $this->media($event, 'newest.jpg', 'image', '1200', '800');
        $middle = $this->media($event, 'middle.jpg', 'video', '1920', '1080');
        $otherUsersMedia = $this->media($event, 'other.jpg', 'image', '400', '300');

        Entitlement::create([
            'user_id' => $user->id,
            'media_id' => $oldThenNewest->id,
            'source' => 'purchase',
            'granted_at' => now()->subDays(10),
        ]);
        Entitlement::create([
            'user_id' => $user->id,
            'media_id' => $middle->id,
            'source' => 'purchase',
            'granted_at' => now()->subDays(2),
        ]);
        Entitlement::create([
            'user_id' => $user->id,
            'media_id' => $oldThenNewest->id,
            'source' => 'purchase',
            'granted_at' => now()->subHour(),
        ]);
        Entitlement::create([
            'user_id' => $otherUser->id,
            'media_id' => $otherUsersMedia->id,
            'source' => 'purchase',
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users/downloads')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'preview_url', 'full_url', 'filename', 'type', 'width', 'height', 'price', 'download_url'],
                ],
            ]);

        $this->assertStringContainsString('/api/v1/media/'.$oldThenNewest->id.'/preview?', $response->json('data.0.preview_url'));
        $this->assertStringContainsString('signature=', $response->json('data.0.preview_url'));

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$oldThenNewest->id, $middle->id], $ids);
        $this->assertCount(2, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertNotContains($otherUsersMedia->id, $ids);
        $this->assertSame([
            'id' => $oldThenNewest->id,
            'preview_url' => $response->json('data.0.preview_url'),
            'full_url' => $response->json('data.0.full_url'),
            'download_url' => '/api/v1/download/'.$oldThenNewest->id,
            'filename' => 'newest.jpg',
            'type' => 'image',
            'width' => '1200',
            'height' => '800',
            'price' => 1,
        ], $response->json('data.0'));
        $this->assertStringContainsString('/purchased-original?', $response->json('data.0.full_url'));
        $this->assertStringNotContainsString('originals/newest.jpg', $response->json('data.0.full_url'));
    }

    public function test_original_display_url_is_signed_and_entitlement_is_rechecked(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('originals/purchased.png', 'original bytes');
        $buyer = User::factory()->create();
        $owner = User::factory()->create();
        $event = Events::create(['user_id' => $owner->id, 'is_active' => true]);
        $media = $this->media($event, 'previews/purchased.png', 'image', '400', '300');
        $media->update(['full_url' => 'originals/purchased.png', 'original_disk' => 'media']);
        $grant = Entitlement::create([
            'user_id' => $buyer->id, 'media_id' => $media->id,
            'source' => 'purchase', 'granted_at' => now(),
        ]);
        Sanctum::actingAs($buyer);
        $url = $this->getJson('/api/v1/users/downloads')->assertOk()->json('data.0.full_url');
        $this->assertStringContainsString('no-store', $this->get($url)->assertOk()->headers->get('Cache-Control'));
        $this->get(parse_url($url, PHP_URL_PATH))->assertForbidden();
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
        $grant->delete();
        $this->get($url)->assertForbidden();
        $this->get('/api/v1/download/'.$media->id)->assertForbidden();
    }

    public function test_original_grants_reject_unauthorized_subjects_and_serve_entitled_images_and_videos(): void
    {
        Storage::fake('media');
        $buyer = User::factory()->create();
        $nonBuyer = User::factory()->create();
        $event = Events::create(['user_id' => User::factory()->create()->id, 'is_active' => true]);
        $image = $this->media($event, 'previews/photo.jpg', 'image', '800', '600');
        $video = $this->media($event, 'previews/clip.mp4', 'video', '1920', '1080');
        foreach ([[$image, 'image-original'], [$video, 'video-original']] as [$media, $bytes]) {
            $media->update(['original_disk' => 'media']);
            Storage::disk('media')->put($media->full_url, $bytes);
            Entitlement::create([
                'user_id' => $buyer->id,
                'media_id' => $media->id,
                'source' => 'purchase',
                'granted_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/users/downloads')->assertUnauthorized();
        Sanctum::actingAs($nonBuyer);
        $this->getJson('/api/v1/download/'.$image->id)->assertForbidden();
        $nonBuyerGrant = URL::temporarySignedRoute('media.purchased-original', now()->addMinutes(5), [
            'media' => $image->id,
            'user' => $nonBuyer->id,
        ]);
        $this->get($nonBuyerGrant)->assertForbidden();

        Sanctum::actingAs($buyer);
        $items = collect($this->getJson('/api/v1/users/downloads')->assertOk()->json('data'))->keyBy('id');
        $imageUrl = $items[$image->id]['full_url'];
        $videoUrl = $items[$video->id]['full_url'];
        $imageResponse = $this->get($imageUrl)->assertOk();
        $videoResponse = $this->get($videoUrl)->assertOk();
        $this->assertSame('image-original', file_get_contents($imageResponse->baseResponse->getFile()->getPathname()));
        $this->assertSame('video-original', file_get_contents($videoResponse->baseResponse->getFile()->getPathname()));

        $tampered = preg_replace('/user='.$buyer->id.'/', 'user='.$nonBuyer->id, $imageUrl);
        $this->assertIsString($tampered);
        $this->get($tampered)->assertForbidden();
    }

    public function test_download_types_use_record_metadata_then_known_original_extensions(): void
    {
        $buyer = User::factory()->create();
        $event = Events::create(['user_id' => User::factory()->create()->id, 'is_active' => true]);
        $expected = [];
        foreach ([
            ['photo.jpg', 'image', 'image'],
            ['scan.png', 'image', 'image'],
            ['archive.webp', null, 'image'],
            ['film.mp4', 'video', 'video'],
            ['mystery.bin', null, null],
        ] as [$name, $storedType, $resolvedType]) {
            $media = $this->media($event, 'previews/'.$name, $storedType, '800', '600');
            Entitlement::create([
                'user_id' => $buyer->id, 'media_id' => $media->id,
                'source' => 'purchase', 'granted_at' => now(),
            ]);
            $expected[$media->id] = $resolvedType;
        }

        Sanctum::actingAs($buyer);
        $items = $this->getJson('/api/v1/users/downloads')->assertOk()->json('data');
        foreach ($items as $item) {
            $this->assertSame($expected[$item['id']], $item['type']);
            $this->assertStringContainsString('/purchased-original?', $item['full_url']);
            $this->assertStringNotContainsString('/preview?', $item['full_url']);
        }
    }

    private function media(Events $event, string $previewUrl, ?string $type, string $width, string $height): EventsImges
    {
        return EventsImges::create([
            'event_id' => $event->id,
            'is_active' => '1',
            'price' => '1.00',
            'preview_url' => $previewUrl,
            'full_url' => 'originals/'.basename($previewUrl),
            'type' => $type,
            'width' => $width,
            'height' => $height,
        ]);
    }

    private function allowDuplicateEntitlementsForRegressionCoverage(): void
    {
        if (! Schema::hasTable('entitlements')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('entitlements'))->pluck('name');
        if (! $indexes->contains('entitlements_user_media_unique')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE entitlements DROP INDEX entitlements_user_media_unique');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS entitlements_user_media_unique');
    }
}
