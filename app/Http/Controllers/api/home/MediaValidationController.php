<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Services\PhotoQualityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MediaValidationController extends Controller
{
    public function __construct(private readonly PhotoQualityService $photoQualityService)
    {
    }

    public function validatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photography_type' => ['required', 'in:normal,professional'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20460'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'accepted' => false,
                'status' => 'rejected',
                'message' => 'Photo rejected',
                'metrics' => [],
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        $result = $this->photoQualityService->validate(
            $request->file('photo'),
            (string) $request->input('photography_type')
        );

        return response()->json($result);
    }
}
