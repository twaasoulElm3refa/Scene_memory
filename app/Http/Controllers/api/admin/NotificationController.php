<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SendEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{
    use ApiResponse;

    public function create(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        User::select('id', 'email')->chunk(100, function ($users) use ($request) {
            Notification::send($users, new SendEmailNotification($request->message));
        });
        return $this->success([], 'Emails sent successfully');
    }
}
