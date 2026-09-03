<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecialCoverageCityRequest;
use App\Services\CityCreationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SpecialCoverageCityController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CityCreationService $service) {}

    public function store(StoreSpecialCoverageCityRequest $request)
    {
        try {
            $city = $this->service->createOrFind(
                $request->validated('name'),
                $request->integer('country_id'),
                app()->getLocale()
            );

            return $this->success([
                'id' => $city->id,
                'country_id' => $city->country_id,
                'name' => $city->name,
                'slug' => $city->slug,
                'translation' => $city->translation,
                'created' => $city->wasRecentlyCreated,
            ], 'City is ready for selection.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('special_coverage_city_create_failed', [
                'user_id' => $request->user()?->id,
                'country_id' => $request->integer('country_id'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->error('Unable to create the city. Please try again.');
        }
    }
}
