<?php

use App\Models\Idea;

it('BR-8 ranks ideas with more votes first', function () {
    $few = Idea::factory()->create(['votes_count' => 1]);
    $many = Idea::factory()->create(['votes_count' => 9]);
    $some = Idea::factory()->create(['votes_count' => 4]);

    expect(Idea::mostVoted()->pluck('id')->all())->toBe([$many->id, $some->id, $few->id]);
});

it('BR-8 breaks vote ties by the oldest submitted_at first', function () {
    $newest = Idea::factory()->create(['votes_count' => 2, 'submitted_at' => now()]);
    $oldest = Idea::factory()->create(['votes_count' => 2, 'submitted_at' => now()->subDays(3)]);
    $middle = Idea::factory()->create(['votes_count' => 2, 'submitted_at' => now()->subDay()]);

    expect(Idea::mostVoted()->pluck('id')->all())->toBe([$oldest->id, $middle->id, $newest->id]);
});

it('BR-8 lists the newest submissions first with the newest scope', function () {
    $old = Idea::factory()->create(['submitted_at' => now()->subDays(2)]);
    $new = Idea::factory()->create(['submitted_at' => now()]);

    expect(Idea::newest()->pluck('id')->all())->toBe([$new->id, $old->id]);
});
