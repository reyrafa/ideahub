<?php

namespace App\Filament\Widgets;

use App\Models\Idea;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * F7: ideas submitted in the last 7 days, compared with the 7 days before (withdrawn ideas excluded).
 */
class NewIdeasThisWeek extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = CarbonImmutable::now();
        $weekAgo = $now->subDays(7);

        $thisWeek = Idea::query()->where('submitted_at', '>', $weekAgo)->count();
        $lastWeek = Idea::query()
            ->where('submitted_at', '>', $now->subDays(14))
            ->where('submitted_at', '<=', $weekAgo)
            ->count();

        $difference = $thisWeek - $lastWeek;

        return [
            Stat::make('New ideas this week', $thisWeek)
                ->description($this->describeChange($difference, $lastWeek))
                ->descriptionIcon($difference >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($difference >= 0 ? 'success' : 'danger'),
        ];
    }

    private function describeChange(int $difference, int $lastWeek): string
    {
        $sign = $difference > 0 ? '+' : '';

        return "{$sign}{$difference} vs last week ({$lastWeek})";
    }
}
