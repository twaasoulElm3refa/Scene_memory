<?php

namespace App\Services\EventModeration;

use App\Jobs\ReviewEventRequestWithAi;
use App\Mail\AccountBannedMail;
use App\Mail\ApproveMail;
use App\Mail\EventNeedsManualReviewMail;
use App\Mail\RejectMail;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\User;
use App\Services\EventTagCacheService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EventRequestModerationService
{
    public const BAN_PROHIBITED_CATEGORIES = [
        'military_content',
        'political_content',
        'sexual_content',
        'profanity_obscene_content',
    ];

    public function __construct(private readonly EventTagCacheService $cache) {}

    public function approveManually(int $requestId): EventRequestCreate
    {
        return $this->applyManualDecision($requestId, 'approved');
    }

    public function rejectManually(int $requestId, string $reason): EventRequestCreate
    {
        return $this->applyManualDecision($requestId, 'rejected', $reason);
    }

    public function markProcessing(int $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            $request = EventRequestCreate::query()
                ->lockForUpdate()
                ->find($requestId);

            if (
                $request === null
                || $request->status !== 'pending'
                || $request->ai_reviewed_at !== null
            ) {
                return false;
            }

            $request->ai_review_status = 'processing';
            $request->ai_attempts = ((int) $request->ai_attempts) + 1;
            $request->save();

            return true;
        });
    }

    public function markFailed(int $requestId): void
    {
        EventRequestCreate::query()
            ->whereKey($requestId)
            ->where('status', 'pending')
            ->whereNull('ai_reviewed_at')
            ->update(['ai_review_status' => 'failed']);

        $request = EventRequestCreate::query()->find($requestId);

        if ($request !== null) {
            $this->cache->invalidateModerationState((int) $request->event_id);
        }
    }

    public function applyAiDecision(int $requestId, ModerationDecision $decision): bool
    {
        $result = DB::transaction(function () use ($requestId, $decision): ?array {
            $request = EventRequestCreate::query()
                ->lockForUpdate()
                ->find($requestId);

            if (
                $request === null
                || $request->status !== 'pending'
                || $request->ai_reviewed_at !== null
            ) {
                return null;
            }

            $event = Events::query()
                ->with('user')
                ->lockForUpdate()
                ->find($request->event_id);

            if ($event === null) {
                throw (new ModelNotFoundException)->setModel(Events::class, [$request->event_id]);
            }

            $request->ai_decision = $decision->decision;
            $request->ai_confidence = $decision->confidence;
            $request->ai_reason = $decision->reason;
            $request->ai_raw_response = $decision->rawResponse;
            $request->ai_reviewed_at = now();
            $request->ai_review_status = 'completed';
            $request->ai_workflow_execution_id = $decision->workflowExecutionId;

            $ownerBanned = false;
            $owner = $event->user;

            if ($decision->decision === 'approved') {
                $request->status = 'approved';
                $event->is_active = true;
                $event->save();
            } elseif ($decision->decision === 'rejected') {
                $request->status = 'rejected';
                $event->is_active = false;
                $event->save();

                if ($this->shouldBanOwner($decision, $owner)) {
                    $wasAlreadyBanned = (bool) $owner->is_banned;
                    $owner->ban($decision->reason);
                    $ownerBanned = ! $wasAlreadyBanned;
                }
            }

            $request->save();

            return [
                'request' => $request->fresh(),
                'event' => $event,
                'decision' => $decision->decision,
                'owner' => $owner,
                'owner_banned' => $ownerBanned,
            ];
        });

        if ($result === null) {
            return false;
        }

        /** @var EventRequestCreate $request */
        $request = $result['request'];
        /** @var Events $event */
        $event = $result['event'];

        $this->cache->invalidateModerationState((int) $event->id);

        if ($result['decision'] === 'approved') {
            $this->queueOwnerMail($request, $event, new ApproveMail($event));

            ReviewEventRequestWithAi::dispatch(
                (int) $request->id,
                translationOnly: true
            )->afterCommit();
        } elseif ($result['decision'] === 'rejected') {
            $this->queueOwnerMail(
                $request,
                $event,
                new RejectMail($event, $decision->reason)
            );

            if ($result['owner_banned'] && $result['owner'] !== null) {
                $this->queueAccountBannedMail($result['owner'], $decision->reason);
            }
        } else {
            $this->queueManualReviewMail($request);
        }

        return true;
    }

    public function shouldBanOwner(ModerationDecision $decision, ?User $owner): bool
    {
        if ($owner === null || $owner->role === 'admin') {
            return false;
        }

        if ($decision->decision !== 'rejected') {
            return false;
        }

        $threshold = min(1, max(0, (float) config(
            'event_moderation.auto_decision_threshold',
            0.70
        )));

        if ($decision->confidence < $threshold) {
            return false;
        }

        $reasonCode = strtolower(trim((string) $decision->reasonCode));
        $matchesReasonCode = in_array($reasonCode, self::BAN_PROHIBITED_CATEGORIES, true);

        $matchesPolicyCategories = count(array_intersect(
            $decision->policyCategories,
            self::BAN_PROHIBITED_CATEGORIES
        )) > 0;

        return $matchesReasonCode || $matchesPolicyCategories;
    }

    private function applyManualDecision(
        int $requestId,
        string $decision,
        string $reason = ''
    ): EventRequestCreate {
        $result = DB::transaction(function () use ($requestId, $decision): array {
            $request = EventRequestCreate::query()
                ->lockForUpdate()
                ->findOrFail($requestId);
            $event = Events::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($request->event_id);

            $targetActive = $decision === 'approved';
            $changed = $request->status !== $decision
                || (bool) $event->is_active !== $targetActive;

            $request->status = $decision;
            $request->save();
            $event->is_active = $targetActive;
            $event->save();

            return compact('request', 'event', 'changed');
        });

        /** @var EventRequestCreate $request */
        $request = $result['request'];
        /** @var Events $event */
        $event = $result['event'];

        $this->cache->invalidateModerationState((int) $event->id);

        if ($result['changed']) {
            $mail = $decision === 'approved'
                ? new ApproveMail($event)
                : new RejectMail($event, $reason);

            $this->queueOwnerMail($request, $event, $mail);

            if ($decision === 'approved') {
                ReviewEventRequestWithAi::dispatch(
                    (int) $request->id,
                    translationOnly: true
                )->afterCommit();
            }
        }

        return $request->fresh();
    }

    private function queueOwnerMail(
        EventRequestCreate $request,
        Events $event,
        Mailable $mail
    ): void {
        $email = trim((string) $event->user?->email);

        if ($email === '') {
            Log::warning('event_request_moderation_owner_email_missing', [
                'request_id' => $request->id,
                'event_id' => $event->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->queue($mail);
        } catch (Throwable $exception) {
            Log::error('event_request_moderation_owner_mail_failed', [
                'request_id' => $request->id,
                'event_id' => $event->id,
                'mail' => $mail::class,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function queueAccountBannedMail(User $user, string $reason): void
    {
        $email = trim((string) $user->email);

        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->queue(new AccountBannedMail($user, $reason));
        } catch (Throwable $exception) {
            Log::error('event_request_moderation_account_banned_mail_failed', [
                'user_id' => $user->id,
                'email' => $email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function queueManualReviewMail(EventRequestCreate $request): void
    {
        $email = trim((string) config('event_moderation.admin_email'));

        if ($email === '') {
            Log::warning('event_request_moderation_admin_email_missing', [
                'request_id' => $request->id,
                'event_id' => $request->event_id,
            ]);

            return;
        }

        try {
            Mail::to($email)->queue(new EventNeedsManualReviewMail($request));
        } catch (Throwable $exception) {
            Log::error('event_request_moderation_admin_mail_failed', [
                'request_id' => $request->id,
                'event_id' => $request->event_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
