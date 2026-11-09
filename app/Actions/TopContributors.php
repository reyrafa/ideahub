<?php

namespace App\Actions;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * F10: contributors ranked by Approved + Implemented ideas submitted in the current calendar quarter.
 *
 * BR-7: anonymous ideas never count. Withdrawn ideas never count (SoftDeletes scope).
 * Ties go to whoever qualified first, then to the name.
 */
class TopContributors
{
    public const LIMIT = 10;

    /**
     * @return Builder<User>
     */
    public function query(?CarbonImmutable $now = null): Builder
    {
        $now ??= CarbonImmutable::now();

        $qualifying = Idea::query()
            ->select('user_id')
            ->selectRaw('count(*) as qualifying_ideas_count')
            ->selectRaw('min(submitted_at) as first_qualified_at')
            ->whereIn('status', [IdeaStatus::Approved->value, IdeaStatus::Implemented->value])
            ->where('is_anonymous', false)
            ->whereBetween('submitted_at', [$now->startOfQuarter(), $now->endOfQuarter()])
            ->groupBy('user_id');

        return User::query()
            ->joinSub($qualifying, 'qualifying', 'qualifying.user_id', '=', 'users.id')
            ->select('users.*', 'qualifying.qualifying_ideas_count', 'qualifying.first_qualified_at')
            ->orderByDesc('qualifying.qualifying_ideas_count')
            ->orderBy('qualifying.first_qualified_at')
            ->orderBy('users.name')
            ->limit(self::LIMIT);
    }
}
