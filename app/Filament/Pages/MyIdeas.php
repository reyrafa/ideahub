<?php

namespace App\Filament\Pages;

use App\Actions\WithdrawIdea;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * F6: the signed-in user's own ideas, anonymous ones included.
 */
class MyIdeas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'My ideas';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'my-ideas';

    protected ?string $heading = 'My ideas';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Idea::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit idea')
                ->icon(Heroicon::Plus)
                ->url(IdeaResource::getUrl('create'))
                ->visible(fn (): bool => Gate::allows('create', Idea::class)),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Idea::query()
                ->where('user_id', auth()->id())
                ->with('category'))
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('medium')
                    ->url(fn (Idea $record): string => IdeaResource::getUrl('view', ['record' => $record])),
                TextColumn::make('is_anonymous')
                    ->label('Visibility')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Idea $record): ?string => $record->is_anonymous ? 'Private (anonymous)' : null),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('votes_count')
                    ->label('Votes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Idea $record): string => IdeaResource::getUrl('view', ['record' => $record])),
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Idea $record): bool => Gate::allows('update', $record))
                    ->url(fn (Idea $record): string => IdeaResource::getUrl('edit', ['record' => $record])),
                Action::make('withdraw')
                    ->label('Withdraw')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Withdraw idea')
                    ->modalDescription('The idea will be removed from the board. This cannot be undone.')
                    ->visible(fn (Idea $record): bool => Gate::allows('delete', $record))
                    ->action(function (Idea $record): void {
                        /** @var User $user */
                        $user = auth()->user();

                        app(WithdrawIdea::class)->handle($user, $record);

                        Notification::make()->title('Idea withdrawn')->success()->send();
                    }),
            ])
            ->emptyStateHeading("You haven't submitted any ideas yet")
            ->emptyStateIcon(Heroicon::OutlinedLightBulb)
            ->emptyStateActions([
                Action::make('submitFirst')
                    ->label('Submit idea')
                    ->icon(Heroicon::Plus)
                    ->url(IdeaResource::getUrl('create'))
                    ->visible(fn (): bool => Gate::allows('create', Idea::class)),
            ]);
    }
}
