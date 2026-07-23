<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                    '*' => ['id', 'preview_url', 'type', 'width', 'height', 'download_url'],
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$oldThenNewest->id, $middle->id], $ids);
        $this->assertCount(2, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertNotContains($otherUsersMedia->id, $ids);
        $this->assertSame([
            'id' => $oldThenNewest->id,
            'preview_url' => 'newest.jpg',
            'type' => 'image',
            'width' => '1200',
            'height' => '800',
            'download_url' => '/api/v1/download/'.$oldThenNewest->id,
        ], $response->json('data.0'));
    }

    private function media(Events $event, string $previewUrl, string $type, string $width, string $height): EventsImges
    {
        return EventsImges::create([
            'event_id' => $event->id,
            'is_active' => '1',
            'price' => '1.00',
            'preview_url' => $previewUrl,
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
