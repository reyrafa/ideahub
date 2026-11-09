<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteComment
{
    public function handle(User $user, Comment $comment): void
    {
        Gate::forUser($user)->authorize('delete', $comment);

        $comment->delete();
    }
}
