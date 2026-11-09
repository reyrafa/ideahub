<?php

namespace App\Filament\Widgets;

use App\Actions\TopContributors;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * F10: top contributors this quarter by Approved + Implemented ideas (anonymous ideas do not count, BR-7).
 */
class LeaderboardWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top contributors this quarter';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(TopContributors::class)->query())
            ->paginated(false)
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('name')
                    ->weight('medium'),
                TextColumn::make('qualifying_ideas_count')
                    ->label('Approved and implemented ideas')
                    ->numeric(),
            ])
            ->emptyStateHeading('No approved ideas yet this quarter')
            ->emptyStateDescription('Quarter starting '.CarbonImmutable::now()->startOfQuarter()->format('M j, Y'));
    }
}
