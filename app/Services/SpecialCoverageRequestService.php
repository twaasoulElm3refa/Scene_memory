<?php

namespace App\Services;

use App\Mail\SpecialCoverageApprovedMail;
use App\Mail\SpecialCoverageRejectedMail;
use App\Models\SpecialCoverageRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SpecialCoverageRequestService
{
    public function approve(int $requestId, int $adminId): SpecialCoverageRequest
    {
        $request = DB::transaction(function () use ($requestId, $adminId): SpecialCoverageRequest {
            $request = SpecialCoverageRequest::query()
                ->with('user')
                ->lockForUpdate()
                ->find($requestId);

            if (! $request) {
                throw (new ModelNotFoundException)->setModel(SpecialCoverageRequest::class, [$requestId]);
            }

            $this->ensurePending($request);

            $request->forceFill([
                'status' => SpecialCoverageRequest::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ])->save();

            return $request->fresh(['user', 'reviewer', 'country.translation', 'city.translation']);
        });

        $this->queueMail($request, new SpecialCoverageApprovedMail($request));

        return $request;
    }

    public function reject(int $requestId, int $adminId, string $reason): SpecialCoverageRequest
    {
        $request = DB::transaction(function () use ($requestId, $adminId, $reason): SpecialCoverageRequest {
            $request = SpecialCoverageRequest::query()
                ->with('user')
                ->lockForUpdate()
                ->find($requestId);

            if (! $request) {
                throw (new ModelNotFoundException)->setModel(SpecialCoverageRequest::class, [$requestId]);
            }

            $this->ensurePending($request);

            $request->forceFill([
                'status' => SpecialCoverageRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ])->save();

            return $request->fresh(['user', 'reviewer', 'country.translation', 'city.translation']);
        });

        $this->queueMail($request, new SpecialCoverageRejectedMail($request));

        return $request;
    }

    private function ensurePending(SpecialCoverageRequest $request): void
    {
        if ($request->status !== SpecialCoverageRequest::STATUS_PENDING) {
            throw new RuntimeException('This special coverage request has already been processed.');
        }
    }

    private function queueMail(SpecialCoverageRequest $request, Mailable $mail): void
    {
        $email = trim((string) $request->user?->email);

        if ($email === '') {
            Log::warning('special_coverage_request_user_email_missing', [
                'request_id' => $request->id,
                'user_id' => $request->user_id,
            ]);

            return;
        }

        try {
            Mail::to($email)->queue($mail);
        } catch (Throwable $exception) {
            Log::error('special_coverage_request_mail_failed', [
                'request_id' => $request->id,
                'mail' => $mail::class,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
