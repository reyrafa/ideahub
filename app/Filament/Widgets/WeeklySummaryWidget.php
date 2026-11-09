<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\WeeklySummary;
use Filament\Widgets\Widget;

/**
 * F11: the latest AI weekly summary for reviewers and admins. Rendered as escaped plain text.
 */
class WeeklySummaryWidget extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.weekly-summary';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('ideas.change-status');
    }

    /**
     * @return array{summary: WeeklySummary|null}
     */
    protected function getViewData(): array
    {
        return ['summary' => WeeklySummary::query()->latest('period_end')->latest('id')->first()];
    }
}
