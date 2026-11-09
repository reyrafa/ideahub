<?php

namespace App\Policies;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;

class IdeaPolicy
{
    /**
     * Any user holding a role may browse the board.
     */
    public function viewAny(User $user): bool
    {
        return $user->roles()->exists();
    }

    public function view(User $user, Idea $idea): bool
    {
        return $user->roles()->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('ideas.create');
    }

    /**
     * BR-6: authors edit only while New and unvoted; admins may always edit.
     */
    public function update(User $user, Idea $idea): bool
    {
        if ($user->can('ideas.manage-any')) {
            return true;
        }

        return $this->isAuthor($user, $idea)
            && $user->can('ideas.create')
            && $idea->status === IdeaStatus::New
            && $idea->votes_count === 0
            && ! $idea->votes()->exists();
    }

    /**
     * BR-6: withdrawing (soft delete) is for the author while New, or an admin.
     */
    public function delete(User $user, Idea $idea): bool
    {
        if ($user->can('ideas.manage-any')) {
            return true;
        }

        return $this->isAuthor($user, $idea) && $idea->status === IdeaStatus::New;
    }

    /**
     * BR-2, BR-3: also gates removing a vote.
     */
    public function vote(User $user, Idea $idea): bool
    {
        return $user->can('ideas.vote')
            && ! $this->isAuthor($user, $idea)
            && $idea->status->isVotingOpen();
    }

    /**
     * BR-9: nobody, admins included, reviews their own idea.
     */
    public function changeStatus(User $user, Idea $idea): bool
    {
        return $user->can('ideas.change-status') && ! $this->isAuthor($user, $idea);
    }

    /**
     * Withdrawn ideas are restored by admins only.
     */
    public function restore(User $user, Idea $idea): bool
    {
        return $user->can('ideas.manage-any');
    }

    /**
     * Ideas are never permanently deleted.
     */
    public function forceDelete(User $user, Idea $idea): bool
    {
        return false;
    }

    public function viewAuthor(User $user, Idea $idea): bool
    {
        return $user->can('ideas.view-anonymous-author');
    }

    /**
     * BR-12.
     */
    public function comment(User $user, Idea $idea): bool
    {
        return $user->can('comments.create') && $idea->status->allowsComments();
    }

    private function isAuthor(User $user, Idea $idea): bool
    {
        return $idea->user_id === $user->id;
    }
}
