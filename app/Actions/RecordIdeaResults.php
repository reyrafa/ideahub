<?php

namespace App\Actions;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * F12: records (or replaces) the actual results of an Implemented idea.
 */
class RecordIdeaResults
{
    /**
     * @param  array{hours_saved?: mixed, money_saved?: mixed, results_note?: mixed}  $input
     */
    public function handle(User $user, Idea $idea, array $input): Idea
    {
        DB::transaction(function () use ($user, $idea, $input): void {
            $fresh = Idea::query()->lockForUpdate()->findOrFail($idea->id);

            Gate::forUser($user)->authorize('recordResults', $fresh);

            if ($fresh->status !== IdeaStatus::Implemented) {
                throw ValidationException::withMessages([
                    'status' => 'Results can only be recorded for Implemented ideas.',
                ]);
            }

            $data = $this->validated($input);

            $fresh->forceFill([
                ...$data,
                'results_recorded_by' => $user->id,
                'results_recorded_at' => now(),
            ])->save();
        });

        return $idea->refresh();
    }

    /**
     * @param  array{hours_saved?: mixed, money_saved?: mixed, results_note?: mixed}  $input
     * @return array{hours_saved: mixed, money_saved: mixed, results_note: mixed}
     */
    private function validated(array $input): array
    {
        $data = [
            'hours_saved' => $this->blankToNull($input['hours_saved'] ?? null),
            'money_saved' => $this->blankToNull($input['money_saved'] ?? null),
            'results_note' => $this->blankToNull($input['results_note'] ?? null),
        ];

        Validator::make($data, [
            'hours_saved' => ['nullable', 'numeric', 'min:0', 'max:999999.9', 'required_without:money_saved'],
            'money_saved' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_without:hours_saved'],
            'results_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'hours_saved.required_without' => 'Enter hours saved, money saved, or both.',
            'money_saved.required_without' => 'Enter hours saved, money saved, or both.',
        ])->validate();

        return $data;
    }

    private function blankToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
