<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Notifications\NotificationRepositoryInterface;
use App\Notifications\SendEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly NotificationRepositoryInterface $notificationRepository)
    {
    }

    public function create(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
        $this->notificationRepository->chunkUsers(100, function ($users) use ($request) {
            Notification::send($users, new SendEmailNotification($request->message));
        });
        return $this->success([], 'Emails sent successfully');
    }
}
