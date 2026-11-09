<?php

namespace App\Filament\Resources\Ideas\Pages;

use App\Actions\ChangeIdeaStatus;
use App\Actions\RecordIdeaResults;
use App\Actions\ToggleVote;
use App\Actions\WithdrawIdea;
use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * @property Idea $record
 */
class ViewIdea extends ViewRecord
{
    protected static string $resource = IdeaResource::class;

    private ?bool $hasVoted = null;

    public function getHeading(): string|Htmlable
    {
        return $this->record->title;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(Blade::render(
            '<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>',
            ['color' => $this->record->status->getColor(), 'label' => $this->record->status->getLabel()],
        ));
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->voteAction(),
            EditAction::make(),
            $this->changeStatusAction(),
            $this->recordResultsAction(),
            $this->withdrawAction(),
        ];
    }

    private function voteAction(): Action
    {
        return Action::make('vote')
            ->label(fn (): string => $this->userHasVoted() ? 'Remove vote' : 'Vote')
            ->icon(fn (): Heroicon => $this->userHasVoted() ? Heroicon::HandThumbUp : Heroicon::OutlinedHandThumbUp)
            ->color(fn (): string => $this->userHasVoted() ? 'primary' : 'gray')
            ->disabled(fn (): bool => ! $this->user()->can('vote', $this->record))
            ->action(function (): void {
                app(ToggleVote::class)->handle($this->user(), $this->record);

                $this->hasVoted = null;
                $this->record->refresh();
            });
    }

    private function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Change status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (): bool => $this->user()->can('changeStatus', $this->record)
                && $this->record->status->allowedTransitions() !== [])
            ->modalHeading('Change status')
            ->modalSubmitActionLabel('Change status')
            ->schema([
                Select::make('status')
                    ->label('New status')
                    ->options(fn (): array => collect($this->record->status->allowedTransitions())
                        ->mapWithKeys(fn (IdeaStatus $status): array => [$status->value => $status->getLabel()])
                        ->all())
                    ->required()
                    ->native(false)
                    ->live(),
                Textarea::make('note')
                    ->label('Note')
                    ->helperText('Required when rejecting or implementing an idea (at least 20 characters).')
                    ->rows(4)
                    ->minLength(fn (Get $get): ?int => self::noteIsRequired($get('status')) ? 20 : null)
                    ->required(fn (Get $get): bool => self::noteIsRequired($get('status'))),
            ])
            ->action(function (array $data): void {
                app(ChangeIdeaStatus::class)->handle(
                    $this->user(),
                    $this->record,
                    IdeaStatus::from($data['status']),
                    $data['note'] ?? null,
                );

                $this->record->refresh();
                $this->dispatch('idea-updated');

                Notification::make()
                    ->title("Status changed to {$this->record->status->getLabel()}")
                    ->success()
                    ->send();
            });
    }

    private function recordResultsAction(): Action
    {
        return Action::make('recordResults')
            ->label('Record results')
            ->icon(Heroicon::OutlinedChartBar)
            ->visible(fn (): bool => $this->record->status === IdeaStatus::Implemented
                && $this->user()->can('recordResults', $this->record))
            ->modalHeading('Record results')
            ->modalSubmitActionLabel('Save results')
            ->fillForm(fn (): array => [
                'hours_saved' => $this->record->hours_saved,
                'money_saved' => $this->record->money_saved,
                'results_note' => $this->record->results_note,
            ])
            ->schema([
                TextInput::make('hours_saved')
                    ->label('Hours saved')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.1),
                TextInput::make('money_saved')
                    ->label('Money saved')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Textarea::make('results_note')
                    ->label('Note')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                app(RecordIdeaResults::class)->handle($this->user(), $this->record, $data);

                $this->record->refresh();

                Notification::make()->title('Results recorded')->success()->send();
            });
    }

    private static function noteIsRequired(mixed $status): bool
    {
        return IdeaStatus::tryFrom((string) $status)?->requiresNote() ?? false;
    }

    private function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label('Withdraw')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Withdraw idea')
            ->modalDescription('The idea will be removed from the board. This cannot be undone.')
            ->visible(fn (): bool => $this->user()->can('delete', $this->record))
            ->action(function (): void {
                app(WithdrawIdea::class)->handle($this->user(), $this->record);

                Notification::make()->title('Idea withdrawn')->success()->send();

                $this->redirect(IdeaResource::getUrl('index'));
            });
    }

    private function userHasVoted(): bool
    {
        return $this->hasVoted ??= $this->record->votes()->where('user_id', $this->user()->id)->exists();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
