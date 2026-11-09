<?php

namespace App\Livewire;

use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The status history of an idea page (BR-11), newest change first.
 */
class IdeaStatusHistory extends Component
{
    public Idea $idea;

    public function mount(): void
    {
        Gate::authorize('view', $this->idea);
    }

    #[On('idea-updated')]
    public function refreshIdea(): void
    {
        $this->idea->refresh();
        unset($this->changes);
    }

    /**
     * @return Collection<int, StatusChange>
     */
    #[Computed]
    public function changes(): Collection
    {
        return $this->idea->statusChanges()
            ->with('changedBy')
            ->latest('changed_at')
            ->latest('id')
            ->get();
    }

    /**
     * Who made the change; the submission row would otherwise expose an anonymous author (BR-7).
     */
    public function changedByName(StatusChange $change): string
    {
        /** @var User $viewer */
        $viewer = auth()->user();

        if ($change->changed_by === $this->idea->user_id) {
            $this->idea->loadMissing('author');

            return $this->idea->authorNameFor($viewer);
        }

        return $change->changedBy->name;
    }

    public function render(): View
    {
        return view('livewire.idea-status-history');
    }
}
