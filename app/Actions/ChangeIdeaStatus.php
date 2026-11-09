<?php

namespace App\Actions;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeIdeaStatus
{
    private const MIN_NOTE_LENGTH = 20;

    public function handle(User $user, Idea $idea, IdeaStatus $to, ?string $note = null): StatusChange
    {
        $change = DB::transaction(function () use ($user, $idea, $to, $note): StatusChange {
            $fresh = Idea::query()->lockForUpdate()->findOrFail($idea->id);

            Gate::forUser($user)->authorize('changeStatus', $fresh);

            $from = $fresh->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "An idea cannot move from {$from->getLabel()} to {$to->getLabel()}.",
                ]);
            }

            $note = $this->validatedNote($to, $note);

            $fresh->status = $to;
            $fresh->save();

            return StatusChange::create([
                'idea_id' => $fresh->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);
        });

        $idea->refresh();

        return $change;
    }

    private function validatedNote(IdeaStatus $to, ?string $note): ?string
    {
        $note = $note === null ? null : trim($note);
        $note = $note === '' ? null : $note;

        $rules = $to->requiresNote()
            ? ['required', 'string', 'min:'.self::MIN_NOTE_LENGTH]
            : ['nullable', 'string'];

        Validator::make(['note' => $note], ['note' => $rules])->validate();

        return $note;
    }
}
