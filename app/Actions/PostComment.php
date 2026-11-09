<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PostComment
{
    public function handle(User $user, Idea $idea, string $body): Comment
    {
        Gate::forUser($user)->authorize('comment', $idea);

        $body = trim($body);

        Validator::make(['body' => $body], ['body' => ['required', 'string', 'min:2', 'max:1000']])->validate();

        return Comment::create([
            'idea_id' => $idea->id,
            'user_id' => $user->id,
            'body' => $body,
            'is_hidden' => false,
        ]);
    }
}
