<?php

namespace App\Livewire;

use App\Actions\DeleteComment;
use App\Actions\EditComment;
use App\Actions\HideComment;
use App\Actions\PostComment;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The comment thread of an idea page (F4). A thin wrapper over the Phase 2 comment actions.
 *
 * @property-read Schema $form
 * @property-read Collection<int, Comment> $comments
 */
class IdeaComments extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Idea $idea;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        Gate::authorize('view', $this->idea);

        $this->form->fill();
    }

    #[On('idea-updated')]
    public function refreshIdea(): void
    {
        $this->idea->refresh();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->hiddenLabel()
                    ->placeholder('Add a comment (2 to 1000 characters)')
                    ->required()
                    ->minLength(2)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->statePath('data');
    }

    public function post(): void
    {
        $data = $this->form->getState();

        try {
            app(PostComment::class)->handle($this->user(), $this->idea, (string) $data['body']);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['data.body' => $exception->errors()['body'] ?? []]);
        }

        $this->form->fill();
        unset($this->comments);

        Notification::make()->title('Comment posted')->success()->send();
    }

    public function editCommentAction(): Action
    {
        return Action::make('editComment')
            ->label('Edit')
            ->link()
            ->size(Size::Small)
            ->modalHeading('Edit comment')
            ->modalSubmitActionLabel('Save')
            ->visible(fn (array $arguments): bool => $this->userCan('update', $arguments))
            ->schema([
                Textarea::make('body')
                    ->label('Comment')
                    ->required()
                    ->minLength(2)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->fillForm(fn (array $arguments): array => ['body' => $this->findComment($arguments)?->body])
            ->action(function (array $arguments, array $data): void {
                $comment = $this->findComment($arguments);

                abort_if($comment === null, 404);

                app(EditComment::class)->handle($this->user(), $comment, (string) $data['body']);
                unset($this->comments);

                Notification::make()->title('Comment updated')->success()->send();
            });
    }

    public function deleteCommentAction(): Action
    {
        return Action::make('deleteComment')
            ->label('Delete')
            ->link()
            ->size(Size::Small)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete comment')
            ->visible(fn (array $arguments): bool => $this->userCan('delete', $arguments))
            ->action(function (array $arguments): void {
                $comment = $this->findComment($arguments);

                abort_if($comment === null, 404);

                app(DeleteComment::class)->handle($this->user(), $comment);
                unset($this->comments);

                Notification::make()->title('Comment deleted')->success()->send();
            });
    }

    public function hideCommentAction(): Action
    {
        return Action::make('hideComment')
            ->label('Hide')
            ->link()
            ->size(Size::Small)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Hide comment')
            ->modalDescription('Employees will no longer see this comment. It stays in the database.')
            ->visible(fn (array $arguments): bool => $this->userCan('hide', $arguments)
                && $this->findComment($arguments)?->is_hidden === false)
            ->action(function (array $arguments): void {
                $comment = $this->findComment($arguments);

                abort_if($comment === null, 404);

                app(HideComment::class)->handle($this->user(), $comment);
                unset($this->comments);

                Notification::make()->title('Comment hidden')->success()->send();
            });
    }

    /**
     * The comments the current user may see (BR-14), oldest first, keyed by id.
     *
     * @return Collection<int, Comment>
     */
    #[Computed]
    public function comments(): Collection
    {
        $this->idea->loadMissing('author');

        return $this->idea->comments()
            ->visibleTo($this->user())
            ->with('author')
            ->oldest()
            ->oldest('id')
            ->get()
            ->each(fn (Comment $comment): Comment => $comment->setRelation('idea', $this->idea))
            ->keyBy('id');
    }

    public function render(): View
    {
        return view('livewire.idea-comments', [
            'viewer' => $this->user(),
            'canComment' => $this->user()->can('comment', $this->idea),
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function findComment(array $arguments): ?Comment
    {
        return $this->comments->get((int) ($arguments['comment'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function userCan(string $ability, array $arguments): bool
    {
        $comment = $this->findComment($arguments);

        return $comment !== null && $this->user()->can($ability, $comment);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
