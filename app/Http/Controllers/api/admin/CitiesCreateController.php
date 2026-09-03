<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Services\CityCreationService;
use Illuminate\Validation\ValidationException;

class CitiesCreateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CityCreationService $service) {}

    public function create(CityRequest $request)
    {
        $data = $request->validated();
        try {
            $city = $this->service->createOrFind(
                $data['name'],
                (int) $data['country_id']
            );

            return $this->success(
                $city->load('translations'),
                'City Created Successfully'
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

}
