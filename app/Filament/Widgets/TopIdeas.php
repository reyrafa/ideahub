<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * F7: the five most voted ideas (ties go to the oldest). Authors honour BR-7 via authorNameFor.
 */
class TopIdeas extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top 5 ideas';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Idea::query()->with('author')->mostVoted()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->weight('medium')
                    ->url(fn (Idea $record): string => IdeaResource::getUrl('view', ['record' => $record])),
                TextColumn::make('votes_count')
                    ->label('Votes')
                    ->numeric(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('author_name')
                    ->label('Author')
                    ->state(fn (Idea $record): string => $record->authorNameFor(auth()->user())),
            ])
            ->emptyStateHeading('No ideas yet');
    }
}
