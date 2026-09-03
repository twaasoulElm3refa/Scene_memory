<?php

namespace Tests\Feature\Api\Admin;

use App\Jobs\TranslateCategoryJob;
use App\Jobs\TranslateCityJob;
use App\Jobs\TranslateCountryJob;
use App\Jobs\TranslateSubCategoryJob;
use App\Models\Categories;
use App\Models\Countries;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use ReflectionProperty;
use Tests\TestCase;

class TranslationJobDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Bus::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_creating_a_category_dispatches_its_translation_job(): void
    {
        Cache::shouldReceive('increment')->once()->with('categories:version', 1)->andReturn(1);
        Cache::shouldReceive('tags')->once()->with(['categories'])->andReturnSelf();
        Cache::shouldReceive('flush')->once()->andReturnTrue();

        $response = $this->postJson('/api/v1/categories/create', [
            'name' => 'كوارث طبيعية',
        ]);

        $response->assertOk();

        $category = Categories::where('name', 'كوارث طبيعية')->firstOrFail();

        Bus::assertDispatched(TranslateCategoryJob::class, function (TranslateCategoryJob $job) use ($category) {
            return $job instanceof ShouldQueue
                && $job->afterCommit === true
                && $this->property($job, 'categoryId') === $category->id
                && $this->property($job, 'text') === $category->name;
        });
    }

    public function test_creating_a_subcategory_dispatches_its_translation_job(): void
    {
        Cache::shouldReceive('increment')->once()->with('categories_cache_version')->andReturn(1);
        Cache::shouldReceive('tags')->once()->with(['categories', 'subCategories'])->andReturnSelf();
        Cache::shouldReceive('flush')->once()->andReturnTrue();

        $category = Categories::create([
            'name' => 'طبيعة',
            'slug' => 'nature',
        ]);

        $response = $this->postJson('/api/v1/sub_categories/create', [
            'name' => 'زلازل',
            'category_id' => $category->id,
        ]);

        $response->assertOk();

        Bus::assertDispatched(TranslateSubCategoryJob::class, function (TranslateSubCategoryJob $job) {
            return $job instanceof ShouldQueue
                && $job->afterCommit === true
                && $this->property($job, 'text') === 'زلازل';
        });
    }

    public function test_creating_a_city_dispatches_its_translation_job(): void
    {
        Cache::shouldReceive('tags')->once()->with(['countries'])->andReturnSelf();
        Cache::shouldReceive('tags')->once()->with(['cities'])->andReturnSelf();
        Cache::shouldReceive('flush')->twice()->andReturnTrue();

        $country = Countries::create([
            'code' => 'TR',
            'slug' => 'tr',
        ]);

        $response = $this->postJson('/api/v1/cities/create', [
            'name' => 'أنقرة',
            'country_id' => $country->id,
        ]);

        $response->assertOk();

        Bus::assertDispatched(TranslateCityJob::class, function (TranslateCityJob $job) {
            return $job instanceof ShouldQueue
                && $job->afterCommit === true
                && $this->property($job, 'text') === 'أنقرة';
        });
    }

    public function test_creating_a_country_dispatches_its_translation_job(): void
    {
        Cache::shouldReceive('tags')->once()->with(['countries'])->andReturnSelf();
        Cache::shouldReceive('tags')->once()->with(['cities'])->andReturnSelf();
        Cache::shouldReceive('flush')->twice()->andReturnTrue();

        $response = $this->postJson('/api/v1/countries/create', [
            'code' => 'TR',
        ]);

        $response->assertOk();

        $country = Countries::where('code', 'TR')->firstOrFail();
        $arabicName = 'تركيا';

        Bus::assertDispatched(TranslateCountryJob::class, function (TranslateCountryJob $job) use ($country, $arabicName) {
            return $job instanceof ShouldQueue
                && $job->afterCommit === true
                && $this->property($job, 'countryId') === $country->id
                && $this->property($job, 'text') === $arabicName;
        });
    }

    private function property(object $object, string $name): mixed
    {
        return (new ReflectionProperty($object, $name))->getValue($object);
    }
}
