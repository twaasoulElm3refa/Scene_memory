<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateImageTagsRequest;
use App\Services\GenerateImageTagsService;
use Illuminate\Support\Facades\Log;

class ImageTagsController extends Controller
{
    use ApiResponse;

    public function generate(GenerateImageTagsRequest $request, GenerateImageTagsService $service)
    {
        try {
            $result = $service->handle(
                validated: $request->validated(),
                user: $request->user()
            );

            return $this->success($result, 'Image tags generated successfully.');
        } catch (\Throwable $e) {
            Log::error('image_tags_generate_failed', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return $this->error('Unable to generate image tags right now.');
        }
    }
}
