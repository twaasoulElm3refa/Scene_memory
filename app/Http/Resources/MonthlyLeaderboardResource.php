<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MonthlyLeaderboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'rank' => (int) $this->rank,
            'id' => (int) $this->user->id,
            'name' => $this->user->name,
            'avatar' => $this->avatarUrl($this->user->image),
            'points' => (int) $this->points,
            'badge' => $this->badgeForRank((int) $this->rank),
        ];
    }

    private function avatarUrl(?string $path): ?string
    {
        if (! $path || str_starts_with($path, '/') || filter_var($path, FILTER_VALIDATE_URL)) {
            return $path ?: null;
        }

        return Storage::disk('public')->url($path);
    }

    private function badgeForRank(int $rank): string
    {
        return match ($rank) {
            1 => 'Memory Master',
            2 => 'Story Keeper',
            3 => 'Moment Curator',
            default => 'Memory Contributor',
        };
    }
}
