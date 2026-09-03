<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SpecialCoverageRequest;
use App\Services\SpecialCoverageRequestService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SpecialCoverageRequestController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SpecialCoverageRequestService $service) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                SpecialCoverageRequest::STATUS_PENDING,
                SpecialCoverageRequest::STATUS_APPROVED,
                SpecialCoverageRequest::STATUS_REJECTED,
            ])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = SpecialCoverageRequest::query()
            ->with([
                'user:id,name,email',
                'reviewer:id,name,email',
                'country.translation',
                'city.translation',
            ])
            ->latest();

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($query) use ($search) {
                $query->where('event_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $this->success($query->paginate(10), 'Special coverage requests fetched successfully.');
    }

    public function show(int $id)
    {
        $request = SpecialCoverageRequest::query()
            ->with([
                'user:id,name,email,phone,country',
                'reviewer:id,name,email',
                'country.translation',
                'city.translation',
            ])
            ->find($id);

        if (! $request) {
            return $this->notFound('Special coverage request not found.');
        }

        return $this->success($request, 'Special coverage request retrieved successfully.');
    }

    public function approve(int $id)
    {
        try {
            return $this->success(
                $this->service->approve($id, auth()->id()),
                'Special coverage request approved successfully.'
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('Special coverage request not found.');
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            Log::error('Special coverage approve error: '.$exception->getMessage());

            return $this->error('Something went wrong.');
        }
    }

    public function reject(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'reason' => ['required', 'string', 'max:5000'],
            ]);

            return $this->success(
                $this->service->reject($id, auth()->id(), trim($validated['reason'])),
                'Special coverage request rejected successfully.'
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ModelNotFoundException) {
            return $this->notFound('Special coverage request not found.');
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            Log::error('Special coverage reject error: '.$exception->getMessage());

            return $this->error('Something went wrong.');
        }
    }
}
