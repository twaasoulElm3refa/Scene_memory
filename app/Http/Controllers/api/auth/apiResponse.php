<?php

namespace App\Http\Controllers\api\auth;


trait apiResponse
{
    public function apiResponse($data=null, $message='')
    {
       return response()->json([
           'success' => true,
           'message' => $message,
           'data'    => $data,
       ], 200);
    }

    public function sendError($error)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];

        return response()->json($response,401);
    }
}
