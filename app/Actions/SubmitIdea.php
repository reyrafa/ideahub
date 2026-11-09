<?php

namespace App\Actions;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use App\Rules\IdeaRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SubmitIdea
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): Idea
    {
        Gate::forUser($user)->authorize('create', Idea::class);

        $validated = Validator::make($data, IdeaRules::rules())->validate();

        return DB::transaction(function () use ($user, $validated): Idea {
            $idea = new Idea([
                ...$validated,
                'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
                'user_id' => $user->id,
                'status' => IdeaStatus::New,
                'submitted_at' => now(),
            ]);
            $idea->votes_count = 0;
            $idea->save();

            StatusChange::create([
                'idea_id' => $idea->id,
                'from_status' => null,
                'to_status' => IdeaStatus::New,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            return $idea;
        });
    }
}
