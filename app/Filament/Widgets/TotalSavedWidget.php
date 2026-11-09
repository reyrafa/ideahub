<?php

namespace App\Filament\Widgets;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * F12: total hours and money saved across Implemented ideas (withdrawn ideas excluded).
 * Aggregate only, so it exposes no author information.
 */
class TotalSavedWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $totals = Idea::query()
            ->where('status', IdeaStatus::Implemented)
            ->selectRaw('COALESCE(SUM(hours_saved), 0) as hours, COALESCE(SUM(money_saved), 0) as money')
            ->toBase()
            ->first();

        $hours = (float) ($totals->hours ?? 0);
        $money = (float) ($totals->money ?? 0);

        return [
            Stat::make('Hours saved', number_format($hours, 1))
                ->description('Across implemented ideas')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('success'),
            Stat::make('Money saved', number_format($money, 2))
                ->description('Across implemented ideas')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }
}
