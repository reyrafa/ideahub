<?php

namespace App\Actions;

use App\Models\Idea;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ToggleVote
{
    /**
     * Add or remove the user's vote and return whether the user now has voted.
     */
    public function handle(User $user, Idea $idea): bool
    {
        return DB::transaction(function () use ($user, $idea): bool {
            $fresh = Idea::query()->lockForUpdate()->findOrFail($idea->id);

            Gate::forUser($user)->authorize('vote', $fresh);

            $existing = Vote::query()
                ->where('idea_id', $fresh->id)
                ->where('user_id', $user->id);

            if ($existing->exists()) {
                if ($existing->delete() === 1) {
                    $fresh->decrement('votes_count');
                }

                $hasVoted = false;
            } else {
                $inserted = Vote::query()->insertOrIgnore([
                    'idea_id' => $fresh->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);

                if ($inserted === 1) {
                    $fresh->increment('votes_count');
                }

                $hasVoted = true;
            }

            $idea->forceFill(['votes_count' => $fresh->votes_count])->syncOriginalAttribute('votes_count');

            return $hasVoted;
        });
    }
}
