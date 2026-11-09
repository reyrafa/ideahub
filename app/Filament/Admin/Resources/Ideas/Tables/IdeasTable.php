<?php

namespace App\Filament\Admin\Resources\Ideas\Tables;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IdeasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['author', 'category']))
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('votes_count')
                    ->label('Votes')
                    ->numeric(),
                TextColumn::make('author_name')
                    ->label('Author')
                    ->state(fn (Idea $record): string => self::authorLabel($record)),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Withdrawn')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('Not withdrawn'),
            ])
            ->filters([
                TrashedFilter::make()
                    ->default(false),
                SelectFilter::make('status')
                    ->options(IdeaStatus::class),
            ])
            ->recordActions([
                RestoreAction::make()
                    ->successNotificationTitle('Idea restored'),
            ])
            ->emptyStateHeading('No withdrawn ideas')
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox);
    }

    /**
     * Admins see the real author; anonymous ideas are flagged, matching the Phase 4 infolist.
     */
    private static function authorLabel(Idea $idea): string
    {
        $name = $idea->authorNameFor(auth()->user());

        return $idea->is_anonymous && $name !== 'Anonymous' ? "{$name} (anonymous)" : $name;
    }
}
