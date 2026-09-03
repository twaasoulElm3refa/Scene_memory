<?php

namespace App\Services;

use App\Jobs\TranslateCityJob;
use App\Models\Cities;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Support\EventTranslationLocales;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CityCreationService
{
    public function __construct(
        private readonly CityRepositoryInterface $cityRepository,
        private readonly LocationCacheService $cache
    ) {}

    public function createOrFind(string $name, int $countryId, string $sourceLocale = 'ar'): Cities
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        try {
            $city = DB::transaction(function () use ($name, $countryId) {
                $existing = $this->cityRepository->findByNormalizedName($name, true);

                if ($existing) {
                    return $this->ensureSameCountry($existing, $countryId);
                }

                return $this->cityRepository->create([
                    'name' => $name,
                    'country_id' => $countryId,
                    'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $city = $this->cityRepository->findByNormalizedName($name);

            if (! $city) {
                throw ValidationException::withMessages([
                    'name' => ['A city with this name already exists.'],
                ]);
            }

            $city = $this->ensureSameCountry($city, $countryId);
        }

        if ($city->wasRecentlyCreated) {
            $locale = in_array($sourceLocale, EventTranslationLocales::ALL, true)
                ? $sourceLocale
                : 'ar';

            $this->cache->invalidate();

            try {
                TranslateCityJob::dispatch($city->id, $city->name, $locale)->afterCommit();
            } catch (Throwable $exception) {
                Log::warning('city_translation_dispatch_failed', [
                    'city_id' => $city->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $city->load('translation');
    }

    private function ensureSameCountry(Cities $city, int $countryId): Cities
    {
        if ((int) $city->country_id !== $countryId) {
            throw ValidationException::withMessages([
                'name' => ['A city with this name already belongs to another country.'],
            ]);
        }

        return $city;
    }
}
