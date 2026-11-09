<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class HideComment
{
    public function handle(User $user, Comment $comment): Comment
    {
        Gate::forUser($user)->authorize('hide', $comment);

        $comment->update([
            'is_hidden' => true,
            'hidden_by' => $user->id,
            'hidden_at' => now(),
        ]);

        return $comment;
    }
}
