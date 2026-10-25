<?php

namespace Tests\Feature\Api\Plans;

use App\Exceptions\CommerceException;
use App\Models\CreditTransaction;
use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\User;
use App\Models\UserCreditGrant;
use App\Models\Wallet;
use App\Services\PlanQuotaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Opt-in race test for a disposable MySQL database; no database resets. */
class PlanQuotaConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('PLANS_MYSQL_CONCURRENCY')) {
            $this->markTestSkipped('Requires an isolated MySQL database with PLANS_MYSQL_CONCURRENCY=1.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringStartsWith('scemory_plans_test', config('database.connections.mysql.database'));
    }

    public function test_competing_redemptions_cannot_overspend_shared_or_tier_quota(): void
    {
        $suffix = uniqid();
        $buyer = User::factory()->create();
        $creator = User::factory()->create();
        Wallet::create(['user_id' => $creator->id, 'amount' => '0.00', 'balance_minor' => 0, 'currency' => 'USD']);
        $tier = MediaTier::create([
            'name' => 'Race tier '.$suffix,
            'rank' => ((int) MediaTier::withTrashed()->max('rank')) + 1,
            'min_price_photo' => 0,
            'max_price_photo' => 2,
            'payout_cap_photo' => 1,
            'min_price_video' => 0,
            'max_price_video' => 5,
            'payout_cap_video' => 3,
            'is_active' => true,
            'is_plan_eligible' => true,
        ]);
        $plan = LicenceType::create([
            'name' => 'Race plan '.$suffix,
            'slug' => 'race-plan-'.$suffix,
            'price' => '10.00',
            'media_tier_id' => $tier->id,
            'photo_quota' => 1,
            'video_quota' => 0,
            'is_active' => true,
        ]);
        $event = Events::create(['title' => 'Race event', 'user_id' => $creator->id, 'is_active' => true]);
        $media = collect(range(1, 6))->map(fn ($index) => EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => "race-{$suffix}-{$index}.jpg",
            'type' => 'image',
            'price' => '1.00',
            'is_active' => true,
            'media_tier_id' => $tier->id,
            'is_plan_redeemable' => true,
        ]));
        $grant = UserCreditGrant::create([
            'user_id' => $buyer->id,
            'licence_id' => $plan->id,
            'media_tier_id' => $tier->id,
            'tier_rank' => $tier->rank,
            'tier_name' => $tier->name,
            'photo_count' => 1,
            'video_count' => 0,
            'remaining_photos' => 1,
            'remaining_videos' => 0,
            'max_eligible_photo_price' => '2.00',
            'max_eligible_video_price' => '5.00',
            'payout_cap_photo' => '1.00',
            'payout_cap_video' => '3.00',
            'payment_method' => 'wallet',
            'payment_reference' => 'race-'.$suffix,
            'plan_price_paid_minor' => 1000,
            'currency' => 'USD',
            'tier_limits_snapshot' => [[
                'media_tier_id' => $tier->id,
                'tier_name' => $tier->name,
                'tier_rank' => $tier->rank,
                'photo_maximum' => 1,
                'video_maximum' => 0,
                'payout_cap_photo_minor' => 100,
                'payout_cap_video_minor' => 300,
                'max_price_photo_minor' => 200,
                'max_price_video_minor' => 500,
            ]],
            'tier_usage_snapshot' => [[
                'media_tier_id' => $tier->id,
                'photos' => 0,
                'videos' => 0,
            ]],
        ]);

        DB::disconnect();
        $children = [];
        foreach ($media as $item) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    app(PlanQuotaService::class)->redeemMedia(User::findOrFail($buyer->id), EventsImges::findOrFail($item->id));
                    exit(0);
                } catch (CommerceException) {
                    exit(0);
                } catch (\Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage().PHP_EOL);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();

        $grant->refresh();
        $this->assertSame(0, $grant->remaining_photos);
        $this->assertSame(1, collect($grant->tier_usage_snapshot)->first()['photos']);
        $this->assertSame(1, Entitlement::where('user_id', $buyer->id)->whereIn('media_id', $media->pluck('id'))->count());
        $this->assertSame(1, CreditTransaction::where('grant_id', $grant->id)->where('action', 'redemption')->count());
    }
}
