<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserDataDeletionRequest;
use App\Services\UserDataDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AdminUserDataDeletionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserDataDeletionService $deletionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = UserDataDeletionRequest::with('user:id,name,email,role')
            ->latest('id');

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('request_type') && $request->input('request_type') !== '') {
            $query->where('request_type', $request->input('request_type'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->paginate($perPage);

        return $this->success($paginated, 'Deletion requests retrieved.');
    }

    public function show(int $id): JsonResponse
    {
        $record = UserDataDeletionRequest::with('user')->find($id);

        if (! $record) {
            return $this->notFound('Deletion request not found.');
        }

        return $this->success($record);
    }

    public function process(Request $request, int $id): JsonResponse
    {
        $record = UserDataDeletionRequest::find($id);

        if (! $record) {
            return $this->notFound('Deletion request not found.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($validated['admin_notes'])) {
            $record->admin_notes = $validated['admin_notes'];
            $record->save();
        }

        try {
            $record = $this->deletionService->executeDeletion($record);
            return $this->success($record, 'Deletion request processed successfully by administrator.');
        } catch (Throwable $e) {
            return $this->error('Failed to process deletion: ' . $e->getMessage(), 500);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $record = UserDataDeletionRequest::find($id);

        if (! $record) {
            return $this->notFound('Deletion request not found.');
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $record->update([
            'status' => UserDataDeletionRequest::STATUS_FAILED,
            'admin_notes' => $validated['admin_notes'],
            'failure_reason' => 'Rejected by administrator: ' . $validated['admin_notes'],
        ]);

        return $this->success($record, 'Deletion request rejected.');
    }
}
