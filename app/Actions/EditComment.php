<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class EditComment
{
    public function handle(User $user, Comment $comment, string $body): Comment
    {
        Gate::forUser($user)->authorize('update', $comment);

        $body = trim($body);

        Validator::make(['body' => $body], ['body' => ['required', 'string', 'min:2', 'max:1000']])->validate();

        $comment->update(['body' => $body]);

        return $comment;
    }
}
