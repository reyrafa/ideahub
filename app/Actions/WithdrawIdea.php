<?php

namespace App\Actions;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class WithdrawIdea
{
    public function handle(User $user, Idea $idea): void
    {
        Gate::forUser($user)->authorize('delete', $idea);

        $idea->delete();
    }
}
