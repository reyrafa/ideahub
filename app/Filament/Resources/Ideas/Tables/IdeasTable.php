<?php

namespace App\Filament\Resources\Ideas\Tables;

use App\Actions\ToggleVote;
use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IdeasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['author', 'category'])
                ->withExists(['votes as has_voted' => fn (Builder $votes) => $votes->where('user_id', auth()->id())]))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('votes_count')
                    ->label('Votes')
                    ->numeric(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('author_name')
                    ->label('Author')
                    ->state(fn (Idea $record): string => $record->authorNameFor(auth()->user())),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                SelectFilter::make('status')
                    ->options(IdeaStatus::class),
                SelectFilter::make('sort')
                    ->label('Sort by')
                    ->options([
                        'most_voted' => 'Most voted',
                        'newest' => 'Newest',
                    ])
                    ->default('most_voted')
                    ->selectablePlaceholder(false)
                    // Ordering must run on the base query: filter queries are wrapped in a nested where() where orderBy is ignored.
                    ->baseQuery(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'most_voted') {
                        'newest' => $query->scopes('newest'),
                        default => $query->scopes('mostVoted'),
                    })
                    ->query(fn (Builder $query): Builder => $query),
            ])
            ->recordActions([
                Action::make('vote')
                    ->label(fn (Idea $record): string => $record->has_voted ? 'Remove vote' : 'Vote')
                    ->icon(fn (Idea $record): Heroicon => $record->has_voted ? Heroicon::HandThumbUp : Heroicon::OutlinedHandThumbUp)
                    ->color(fn (Idea $record): string => $record->has_voted ? 'primary' : 'gray')
                    ->disabled(fn (Idea $record): bool => ! auth()->user()?->can('vote', $record))
                    ->action(function (Idea $record): void {
                        /** @var User $user */
                        $user = auth()->user();

                        app(ToggleVote::class)->handle($user, $record);
                    }),
            ])
            ->recordUrl(fn (Idea $record): string => IdeaResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No ideas match your filters')
            ->emptyStateIcon(Heroicon::OutlinedLightBulb)
            ->emptyStateActions([
                Action::make('resetFilters')
                    ->label('Reset filters')
                    ->action(fn ($livewire) => $livewire->resetTableFiltersForm()),
            ]);
    }
}
