<?php

use App\Actions\SubmitIdea;
use App\Enums\IdeaStatus;
use App\Models\Category;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->employee()->create();
});

it('BR-5 validates the title length (10-120 characters)', function (int $length, bool $accepted) {
    $data = validIdeaData(['title' => str_repeat('t', $length)]);

    $errors = validationErrorsFrom(fn () => app(SubmitIdea::class)->handle($this->user, $data));

    if ($accepted) {
        expect($errors)->toBe([])
            ->and(Idea::count())->toBe(1);
    } else {
        expect(array_keys($errors))->toContain('title')
            ->and(Idea::count())->toBe(0);
    }
})->with([
    '5 chars rejected' => [5, false],
    '9 chars rejected' => [9, false],
    '10 chars accepted' => [10, true],
    '120 chars accepted' => [120, true],
    '121 chars rejected' => [121, false],
]);

it('BR-5 validates problem and proposal length (at least 30 characters)', function (string $field, int $length, bool $accepted) {
    $data = validIdeaData([$field => str_repeat('x', $length)]);

    $errors = validationErrorsFrom(fn () => app(SubmitIdea::class)->handle($this->user, $data));

    if ($accepted) {
        expect($errors)->toBe([])
            ->and(Idea::count())->toBe(1);
    } else {
        expect(array_keys($errors))->toContain($field)
            ->and(Idea::count())->toBe(0);
    }
})->with([
    'problem 29 rejected' => ['problem', 29, false],
    'problem 30 accepted' => ['problem', 30, true],
    'proposal 29 rejected' => ['proposal', 29, false],
    'proposal 30 accepted' => ['proposal', 30, true],
]);

it('BR-5 rejects an inactive category', function () {
    $data = validIdeaData(['category_id' => Category::factory()->inactive()->create()->id]);

    $errors = validationErrorsFrom(fn () => app(SubmitIdea::class)->handle($this->user, $data));

    expect(array_keys($errors))->toContain('category_id')
        ->and(Idea::count())->toBe(0);
});

it('BR-5 rejects a category that does not exist', function () {
    $data = validIdeaData(['category_id' => 999999]);

    $errors = validationErrorsFrom(fn () => app(SubmitIdea::class)->handle($this->user, $data));

    expect(array_keys($errors))->toContain('category_id')
        ->and(Idea::count())->toBe(0);
});

it('BR-5 submits a valid idea as New with a timestamp, zero votes and an initial history row', function () {
    $this->freezeSecond();
    $data = validIdeaData(['is_anonymous' => true]);

    $idea = app(SubmitIdea::class)->handle($this->user, $data);

    expect($idea)->toBeInstanceOf(Idea::class)
        ->and($idea->exists)->toBeTrue()
        ->and($idea->user_id)->toBe($this->user->id)
        ->and($idea->status)->toBe(IdeaStatus::New)
        ->and($idea->votes_count)->toBe(0)
        ->and($idea->is_anonymous)->toBeTrue()
        ->and($idea->submitted_at->equalTo(now()))->toBeTrue();

    $history = $idea->statusChanges()->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()->from_status)->toBeNull()
        ->and($history->first()->to_status)->toBe(IdeaStatus::New)
        ->and($history->first()->changed_by)->toBe($this->user->id);
});

it('BR-5 treats expected impact and anonymity as optional', function () {
    $data = validIdeaData();
    unset($data['expected_impact'], $data['is_anonymous']);

    $idea = app(SubmitIdea::class)->handle($this->user, $data);

    expect($idea->is_anonymous)->toBeFalse()
        ->and($idea->expected_impact)->toBeNull();
});
