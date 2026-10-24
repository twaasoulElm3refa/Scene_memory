<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserDataDeletionRequest;
use App\Services\UserDataDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UserDataDeletionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserDataDeletionService $deletionService
    ) {}

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirmation_phrase' => ['required', 'string'],
        ]);

        if (trim(strtoupper($validated['confirmation_phrase'])) !== 'DELETE') {
            return $this->error('You must type DELETE to confirm this action.', 422, [
                'confirmation_phrase' => ['You must type DELETE to confirm this action.'],
            ]);
        }

        $user = $request->user();

        try {
            $record = $this->deletionService->createRequest(
                user: $user,
                type: UserDataDeletionRequest::TYPE_FULL_ACCOUNT,
                reason: $validated['reason'] ?? null,
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->success(
                $this->formatResponse($record),
                'Data deletion request initiated successfully.'
            );
        } catch (Throwable $e) {
            Log::error('Data deletion submit error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to initiate deletion request: ' . $e->getMessage(), 500);
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:10'],
            'confirmation_phrase' => ['required', 'string'],
        ]);

        if (trim(strtoupper($validated['confirmation_phrase'])) !== 'DELETE') {
            return $this->error('You must type DELETE to confirm this action.', 422, [
                'confirmation_phrase' => ['You must type DELETE to confirm this action.'],
            ]);
        }

        $user = $request->user();
        $record = $this->deletionService->getActiveRequestForUser($user);

        if (! $record) {
            return $this->notFound('No active deletion request found.');
        }

        try {
            $record = $this->deletionService->confirmRequest(
                request: $record,
                user: $user,
                password: $validated['password'] ?? null,
                code: $validated['code'] ?? null
            );

            $message = $record->isUnderReview()
                ? 'Your request has been placed under administrative review due to active financial balances.'
                : 'Account and personal data have been deleted successfully.';

            return $this->success($this->formatResponse($record), $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationError($e->errors(), $e->getMessage());
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->forbidden($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Data deletion confirm error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to confirm deletion request: ' . $e->getMessage(), 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $record = $this->deletionService->getActiveRequestForUser($user);

        if (! $record) {
            return $this->success(null, 'No active deletion request.');
        }

        return $this->success($this->formatResponse($record));
    }

    public function resendCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $record = $this->deletionService->getActiveRequestForUser($user);

        if (! $record || ! $record->requires_code) {
            return $this->error('No verification code is required for this request.', 400);
        }

        try {
            $this->deletionService->resendCode($record, $user);
            return $this->success(null, 'A new confirmation code has been sent to your email.');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $record = $this->deletionService->getActiveRequestForUser($user);

        if (! $record) {
            return $this->notFound('No active deletion request found.');
        }

        try {
            $record = $this->deletionService->cancelRequest($record, $user);
            return $this->success($this->formatResponse($record), 'Data deletion request cancelled successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->forbidden($e->getMessage());
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function publicStatus(string $reference): JsonResponse
    {
        $record = UserDataDeletionRequest::where('reference', $reference)->first();

        if (! $record) {
            return $this->notFound('Data deletion request not found.');
        }

        return $this->success([
            'reference' => $record->reference,
            'status' => $record->status,
            'request_type' => $record->request_type,
            'masked_email' => $record->masked_email,
            'financial_obligations_detected' => (bool) $record->financial_obligations_detected,
            'confirmed_at' => $record->confirmed_at?->toIso8601String(),
            'completed_at' => $record->completed_at?->toIso8601String(),
            'cancelled_at' => $record->cancelled_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ], 'Status retrieved.');
    }

    public function facebookCallback(Request $request): JsonResponse
    {
        $ref = 'DEL-FB-' . strtoupper(Str::random(8));

        return response()->json([
            'url' => url('/en/data-deletion?reference=' . $ref),
            'confirmation_code' => $ref,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function formatResponse(UserDataDeletionRequest $request): array
    {
        return [
            'id' => $request->id,
            'reference' => $request->reference,
            'status' => $request->status,
            'request_type' => $request->request_type,
            'requires_code' => (bool) $request->requires_code,
            'masked_email' => $request->masked_email,
            'financial_obligations_detected' => (bool) $request->financial_obligations_detected,
            'financial_hold_reason' => $request->financial_hold_reason,
            'confirmed_at' => $request->confirmed_at?->toIso8601String(),
            'completed_at' => $request->completed_at?->toIso8601String(),
            'cancelled_at' => $request->cancelled_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
