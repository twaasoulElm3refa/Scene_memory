<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/** Administrator review of funds held after PayPal refunds, reversals and disputes. */
class FinancialHoldController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'released'])],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $holds = FinancialHold::query()
            ->with(['user:id,name,email', 'payment:id,order_id,operation,capture_id,amount_minor,currency,status'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($holds)->header('Cache-Control', 'private, no-store');
    }

    public function release(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $hold = DB::transaction(function () use ($request, $id, $validated) {
            $hold = FinancialHold::query()->lockForUpdate()->findOrFail($id);
            if ($hold->status === 'active') {
                $hold->update([
                    'status' => 'released',
                    'resolved_by' => $request->user()->id,
                    'resolved_at' => now(),
                    'resolution_note' => $validated['note'],
                ]);
            }

            return $hold->fresh();
        }, 5);
        Log::info('financial_hold_released', ['hold_id' => $hold->id, 'admin_id' => $request->user()->id]);

        return response()->json(['data' => $hold])->header('Cache-Control', 'private, no-store');
    }
}
