<?php

namespace App\Actions;

use App\Models\Idea;
use App\Models\User;
use App\Rules\IdeaRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateIdea
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, Idea $idea, array $data): Idea
    {
        Gate::forUser($user)->authorize('update', $idea);

        $validated = Validator::make($data, IdeaRules::rules())->validate();

        $idea->update($validated);

        return $idea;
    }
}
