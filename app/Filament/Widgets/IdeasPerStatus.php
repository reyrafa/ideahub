<?php

namespace App\Filament\Widgets;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use Filament\Widgets\ChartWidget;

/**
 * F7: one bar per status, zero-filled; withdrawn ideas are excluded.
 */
class IdeasPerStatus extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Ideas per status';

    protected function getData(): array
    {
        /** @var array<string, int> $counts */
        $counts = Idea::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $statuses = IdeaStatus::cases();

        return [
            'datasets' => [
                [
                    'label' => 'Ideas',
                    'data' => array_map(fn (IdeaStatus $status): int => (int) ($counts[$status->value] ?? 0), $statuses),
                ],
            ],
            'labels' => array_map(fn (IdeaStatus $status): string => $status->getLabel(), $statuses),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
