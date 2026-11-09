<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    private const EDIT_WINDOW_MINUTES = 15;

    public function view(User $user, Comment $comment): bool
    {
        return ! $comment->is_hidden || $user->can('comments.view-hidden');
    }

    /**
     * BR-13.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $this->isAuthorWithinWindow($user, $comment);
    }

    /**
     * BR-13.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $this->isAuthorWithinWindow($user, $comment);
    }

    /**
     * BR-14.
     */
    public function hide(User $user, Comment $comment): bool
    {
        return $user->can('comments.hide');
    }

    private function isAuthorWithinWindow(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            && $comment->created_at !== null
            && $comment->created_at->copy()->addMinutes(self::EDIT_WINDOW_MINUTES)->greaterThanOrEqualTo(now());
    }
}
