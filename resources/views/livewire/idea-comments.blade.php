<div class="idea-comments">
    @if ($this->comments->isEmpty())
        <p style="color: var(--gray-500, #6b7280); margin-bottom: 1rem;">No comments yet.</p>
    @endif

    @foreach ($this->comments as $comment)
        <div wire:key="comment-{{ $comment->id }}" style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200, #e5e7eb);">
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.875rem;">
                <strong>{{ $comment->authorNameFor($viewer) }}</strong>
                <span style="color: var(--gray-500, #6b7280);">{{ $comment->created_at?->diffForHumans() }}</span>
                @if ($comment->is_hidden)
                    <x-filament::badge color="warning" size="sm">Hidden</x-filament::badge>
                @endif
            </div>
            <p style="white-space: pre-line; margin: 0.25rem 0 0.5rem;">{{ $comment->body }}</p>
            <div style="display: flex; gap: 0.75rem;">
                {{ ($this->editCommentAction)(['comment' => $comment->id]) }}
                {{ ($this->deleteCommentAction)(['comment' => $comment->id]) }}
                {{ ($this->hideCommentAction)(['comment' => $comment->id]) }}
            </div>
        </div>
    @endforeach

    <div style="margin-top: 1rem;">
        @if ($canComment)
            <form wire:submit="post">
                {{ $this->form }}

                <div style="margin-top: 0.75rem;">
                    <x-filament::button type="submit">Post comment</x-filament::button>
                </div>
            </form>
        @elseif (! $idea->status->allowsComments())
            <p style="color: var(--gray-500, #6b7280);">Comments are closed</p>
        @endif
    </div>

    <x-filament-actions::modals />
</div>