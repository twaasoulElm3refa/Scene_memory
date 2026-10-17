<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonthlyLeaderboardResource;
use App\Http\Resources\WeeklyLeaderboardResource;
use App\Models\WeeklyLeaderboard;
use App\Services\MonthlyLeaderboardService;
use App\Services\WeeklyCompetitionPeriod;
use App\Services\WeeklyLeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly WeeklyLeaderboardService $leaderboardService,
        private readonly WeeklyCompetitionPeriod $period
    ) {}

    public function weeklyPreview(): JsonResponse
    {
        $start = $this->period->currentWeekStart();

        return response()->json([
            ...$this->period->metadata($start),
            'users' => WeeklyLeaderboardResource::collection($this->leaderboardService->preview(10, $start))->resolve(request()),
        ])->header('Cache-Control', 'no-store');
    }

    public function weekly(Request $request): JsonResponse
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1']);
        $start = $this->period->currentWeekStart();
        $leaders = $this->leaderboardService->currentWeek((int) $request->input('per_page', 15), null, $start);

        return response()->json([
            ...$this->period->metadata($start),
            'users' => WeeklyLeaderboardResource::collection($leaders)->resolve($request),
            'current_page' => $leaders->currentPage(),
            'last_page' => $leaders->lastPage(),
            'total' => $leaders->total(),
        ])->header('Cache-Control', 'no-store');
    }

    public function weeklyHistory(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => 'sometimes|required|date_format:Y-m-d',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        $weeks = DB::table('competition_weeks')->whereNotNull('finalized_at')
            ->where('week_start', '<', $this->period->weekIdentifier());

        if (! $request->has('week_start')) {
            $history = $weeks->orderByDesc('week_start')->paginate((int) $request->input('per_page', 15));
            $history->through(function ($week) {
                $week->finalized_at = CarbonImmutable::parse($week->finalized_at, 'UTC')->toIso8601String();

                return $week;
            });

            return response()->json($history);
        }

        $start = CarbonImmutable::parse($request->input('week_start'), $this->period->timezone());
        if ($this->period->weekIdentifier($start) !== $request->input('week_start')) {
            throw ValidationException::withMessages(['week_start' => 'The competition must start on a Saturday.']);
        }

        $week = $weeks->where('week_start', $start->toDateString())->first();
        abort_unless($week, 404, 'This competition has not been finalized.');
        $leaders = WeeklyLeaderboard::query()->where('week_start', $week->week_start)
            ->orderBy('rank')->with('user:id,name,image')->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'week_start' => $week->week_start,
            'week_end' => $week->week_end,
            'timezone' => $week->timezone,
            'finalized_at' => CarbonImmutable::parse($week->finalized_at, 'UTC')->toIso8601String(),
            'users' => WeeklyLeaderboardResource::collection($leaders)->resolve($request),
            'current_page' => $leaders->currentPage(),
            'last_page' => $leaders->lastPage(),
            'total' => $leaders->total(),
        ]);
    }

    public function monthlyPreview(): JsonResponse
    {
        $period = now();
        $leaders = app(MonthlyLeaderboardService::class)->preview();

        return response()->json([
            'month' => $period->format('F'),
            'year' => $period->year,
            'users' => MonthlyLeaderboardResource::collection($leaders)->resolve(request()),
        ])->header('Deprecation', 'true')
            ->header('Link', '</api/leaderboard/weekly-preview>; rel="successor-version"');
    }
}
