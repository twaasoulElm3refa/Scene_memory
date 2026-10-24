<?php

namespace App\Jobs;

use App\Models\UserDataDeletionRequest;
use App\Services\UserDataDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessUserDataDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $requestId) {}

    public function handle(UserDataDeletionService $deletionService): void
    {
        $request = UserDataDeletionRequest::find($this->requestId);
        if (! $request) {
            Log::warning('UserDataDeletionJob: Request not found', ['request_id' => $this->requestId]);
            return;
        }

        try {
            $deletionService->executeDeletion($request);
        } catch (Throwable $e) {
            Log::error('UserDataDeletionJob failed', [
                'request_id' => $this->requestId,
                'error' => $e->getMessage(),
            ]);

            $request->update([
                'status' => UserDataDeletionRequest::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
