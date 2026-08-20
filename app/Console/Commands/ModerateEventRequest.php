<?php

namespace App\Console\Commands;

use App\Jobs\ReviewEventRequestWithAi;
use App\Models\EventRequestCreate;
use Illuminate\Console\Command;

class ModerateEventRequest extends Command
{
    protected $signature = 'scemory:moderate-event-request {request_id : The pending event request ID}';

    protected $description = 'Queue AI moderation for one specific pending event creation request';

    public function handle(): int
    {
        $requestId = filter_var($this->argument('request_id'), FILTER_VALIDATE_INT);

        if ($requestId === false || $requestId < 1) {
            $this->error('The request ID must be a positive integer.');

            return self::INVALID;
        }

        $request = EventRequestCreate::query()->find($requestId);

        if ($request === null) {
            $this->error("Event request #{$requestId} was not found.");

            return self::FAILURE;
        }

        if ($request->status !== 'pending') {
            $this->warn("Event request #{$requestId} is already {$request->status}; nothing was queued.");

            return self::SUCCESS;
        }

        if ($request->ai_reviewed_at !== null) {
            $this->warn("Event request #{$requestId} already has a completed AI review; nothing was queued.");

            return self::SUCCESS;
        }

        ReviewEventRequestWithAi::dispatch((int) $requestId);
        $this->info("AI moderation queued for event request #{$requestId}.");

        return self::SUCCESS;
    }
}
