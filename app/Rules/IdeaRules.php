<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class IdeaRules
{
    /**
     * The shared BR-5 validation rules for submitting and editing an idea.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:10', 'max:120'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'problem' => ['required', 'string', 'min:30'],
            'proposal' => ['required', 'string', 'min:30'],
            'expected_impact' => ['nullable', 'string'],
            'is_anonymous' => ['boolean'],
        ];
    }
}
