<?php

use App\Actions\GenerateWeeklySummary;
use App\Filament\Widgets\WeeklySummaryWidget;
use App\Models\Category;
use App\Models\Idea;
use App\Models\User;
use App\Models\WeeklySummary;
use App\Rules\IdeaRules;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00:00'));
    config([
        'services.anthropic.key' => 'test-key',
        'services.anthropic.model' => 'test-model',
    ]);
    Http::preventStrayRequests();
});

function fakeClaude(string $text = "Theme one\nTheme two"): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => " {$text} "]],
    ])]);
}

function recentIdea(array $overrides = [], ?User $author = null): Idea
{
    return Idea::factory()
        ->for($author ?? User::factory()->create(), 'author')
        ->create(['submitted_at' => now()->subDays(2), ...$overrides]);
}

function sentBody(): string
{
    $body = '';
    Http::assertSent(function (Request $request) use (&$body) {
        $body = $request->body();

        return true;
    });

    return $body;
}

it('F11 sends idea text but no names, emails or ids', function () {
    $author = User::factory()->create(['name' => 'Zelda Quimby', 'email' => 'zquimby@example.com', 'department' => 'Logistics']);
    $idea = recentIdea(['title' => 'Better coffee', 'problem' => 'Weak brew', 'proposal' => 'Buy beans', 'expected_impact' => 'Happier staff', 'votes_count' => 77], $author);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    $body = sentBody();
    expect($body)
        ->toContain('Better coffee', 'Weak brew', 'Buy beans', 'Happier staff', $idea->category->name)
        ->not->toContain('Zelda', 'Quimby', 'zquimby', 'example.com', 'Logistics', '"id"', 'votes', 'user_id')
        ->not->toContain('77');
});

it('F11 scrubs emails, phone numbers and known names from idea text', function () {
    User::factory()->create(['name' => 'Zelda Quimby', 'email' => 'zq@example.com']);
    recentIdea([
        'problem' => 'Ask zelda quimby or mail bob@corp.test or call +1 (555) 123-4567.',
        'proposal' => 'Ask zq about it',
    ]);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    $body = sentBody();
    expect($body)
        ->toContain('[removed]')
        ->not->toContain('quimby', 'Quimby', 'bob@corp', '555', '4567', 'Ask zq');
});

it('F11 posts to the Messages API with the right headers, model and prompt', function () {
    recentIdea();
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request['model'] === 'test-model'
        && $request['max_tokens'] === 600
        && str_contains($request['system'], 'untrusted')
        && $request['messages'][0]['role'] === 'user');
});

it('F11 saves the trimmed text and replaces the summary when run again for the same period', function () {
    recentIdea();
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'text', 'text' => 'First']]])
        ->push(['content' => [['type' => 'text', 'text' => 'Second']]])]);
    $this->artisan('ideas:summarize-week')->assertSuccessful();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    expect(WeeklySummary::query()->count())->toBe(1)
        ->and(WeeklySummary::query()->first())
        ->summary->toBe('Second')
        ->ideas_count->toBe(1)
        ->model->toBe('test-model');
});

it('F11 only stores the text blocks of the response', function () {
    recentIdea();
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [
        ['type' => 'thinking', 'thinking' => 'secret'],
        ['type' => 'text', 'text' => 'Visible'],
    ]])]);

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    expect(WeeklySummary::query()->value('summary'))->toBe('Visible');
});

it('F11 makes no call and saves nothing when there are no new ideas', function () {
    recentIdea(['submitted_at' => now()->subDays(10)]);
    Http::fake();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    Http::assertNothingSent();
    expect(WeeklySummary::query()->count())->toBe(0);
});

it('F11 is skipped without an API key', function () {
    config(['services.anthropic.key' => '']);
    recentIdea();
    Http::fake();

    $this->artisan('ideas:summarize-week')->expectsOutputToContain('skipped')->assertSuccessful();

    Http::assertNothingSent();
    expect(WeeklySummary::query()->count())->toBe(0);
});

it('F11 saves nothing and fails when the API errors', function (callable $fake) {
    recentIdea();
    $fake();

    $this->artisan('ideas:summarize-week')->assertFailed();

    expect(WeeklySummary::query()->count())->toBe(0);
})->with([
    'server error' => fn () => fn () => Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]),
    'timeout' => fn () => fn () => Http::fake(['api.anthropic.com/*' => Http::failedConnection()]),
    'empty text' => fn () => fn () => Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '  ']]])]),
    'invalid body' => fn () => fn () => Http::fake(['api.anthropic.com/*' => Http::response('not json')]),
]);

it('F11 excludes withdrawn ideas and ideas outside the window', function () {
    recentIdea(['title' => 'Kept idea']);
    recentIdea(['title' => 'Withdrawn idea'])->delete();
    recentIdea(['title' => 'Old idea', 'submitted_at' => now()->subDays(8)]);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    expect(sentBody())->toContain('Kept idea')->not->toContain('Withdrawn idea', 'Old idea');
});

it('F11 includes anonymous ideas without revealing the author', function () {
    $author = User::factory()->create(['name' => 'Hidden Person', 'email' => 'hidden@example.com']);
    recentIdea(['title' => 'Quiet idea', 'is_anonymous' => true], $author);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    expect(sentBody())->toContain('Quiet idea')->not->toContain('Hidden', 'hidden@');
});

it('F11 sends at most 50 ideas and cuts long fields', function () {
    $category = Category::factory()->create();
    Idea::factory()->count(55)->for($category)->create(['submitted_at' => now()->subDay(), 'problem' => str_repeat('x', 3000)]);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    $content = json_decode(sentBody(), true)['messages'][0]['content'];
    expect(substr_count($content, '<idea>'))->toBe(50)
        ->and($content)->not->toContain(str_repeat('x', 1001));
});

it('F11 dry-run prints the payload, calls nothing and saves nothing', function () {
    recentIdea(['title' => 'Dry idea']);
    Http::fake();

    $this->artisan('ideas:summarize-week --dry-run')
        ->expectsOutputToContain('Dry idea')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect(WeeklySummary::query()->count())->toBe(0);
});

it('F11 dry-run works without a key and honours --days', function () {
    config(['services.anthropic.key' => null]);
    recentIdea(['title' => 'Older idea', 'submitted_at' => now()->subDays(20)]);

    $this->artisan('ideas:summarize-week --dry-run')->expectsOutputToContain('No new ideas')->assertSuccessful();
    $this->artisan('ideas:summarize-week --dry-run --days=30')->expectsOutputToContain('Older idea')->assertSuccessful();

    expect(app(GenerateWeeklySummary::class)->payload(30))->not->toBeNull();
});

it('F11 is scheduled for Monday at 06:00 without overlap', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'ideas:summarize-week'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * 1')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(60)
        ->and($event->onOneServer)->toBeTrue();
});

it('F11 shows the latest summary widget to reviewers and admins only', function (string $role, bool $visible) {
    $this->actingAs(User::factory()->{$role}()->create());

    expect(WeeklySummaryWidget::canView())->toBe($visible);
})->with([['employee', false], ['reviewer', true], ['admin', true]]);

it('F11 widget renders the latest summary as escaped text', function () {
    WeeklySummary::factory()->create(['summary' => 'Old one', 'period_end' => '2026-11-01', 'period_start' => '2026-10-25']);
    WeeklySummary::factory()->create(['summary' => '<script>alert(1)</script> Newest', 'period_end' => '2026-11-09', 'period_start' => '2026-11-02']);
    $this->actingAs(User::factory()->reviewer()->create());

    Livewire::test(WeeklySummaryWidget::class)
        ->assertSee('Newest')
        ->assertDontSee('Old one')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;');
});

it('F11 widget shows an empty state and appears on the dashboard for reviewers', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer)->get('/app')->assertSuccessful()->assertSeeLivewire(WeeklySummaryWidget::class);
    Livewire::actingAs($reviewer)->test(WeeklySummaryWidget::class)->assertSee('No weekly summary yet');
});

it('F11 does not leak bypass-style names, emails or phone numbers', function (string $text, array $secrets) {
    User::factory()->create(['name' => 'Zelda Quimby', 'email' => 'zelda.q@example.com']);
    recentIdea(['problem' => "Padding to be long enough. {$text}"]);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    $body = mb_strtolower(sentBody());
    foreach ($secrets as $secret) {
        expect($body)->not->toContain(mb_strtolower($secret));
    }
})->with([
    'nbsp in name' => ["Ask Zelda\u{00A0}Quimby today", ['Zelda', 'Quimby']],
    'double space in name' => ['Ask Zelda  Quimby today', ['Zelda', 'Quimby']],
    'zero-width in name' => ["Ask Zel\u{200B}da Quimby today", ['Quimby']],
    'reversed name' => ['Ask Quimby Zelda today', ['Zelda', 'Quimby']],
    'single name token' => ['Ask quimby today', ['quimby']],
    'email local part' => ['Ask zelda.q about it', ['zelda.q']],
    'at and dot words' => ['Mail jane [at] corp dot com now', ['jane', 'corp']],
    'parenthesised at' => ['Mail jane(at)corp.com now', ['jane', 'corp.com']],
    'full-width at' => ["Mail jane\u{FF20}corp.com now", ['jane', 'corp.com']],
    'accented local part' => ['Mail josé@corp.com now', ['josé', 'corp.com']],
    'full-width digits phone' => ["Call \u{FF15}\u{FF15}\u{FF15}\u{FF11}\u{FF12}\u{FF13}\u{FF14}\u{FF15}\u{FF16}\u{FF17} now", ['5551234567']],
    'email split by angle brackets' => ['Mail <jane@corp.com> now', ['jane@corp.com']],
]);

it('F11 cuts oversized fields before scrubbing and never sends more than the cap', function () {
    recentIdea(['problem' => str_repeat('a ', 50000).'tail-marker']);
    fakeClaude();

    $this->artisan('ideas:summarize-week')->assertSuccessful();

    expect(sentBody())->not->toContain('tail-marker');
});

it('F11 limits problem, proposal and expected impact to 5000 characters', function (string $field) {
    $valid = [
        'title' => 'A valid idea title',
        'category_id' => Category::factory()->create()->id,
        'problem' => str_repeat('p', 40),
        'proposal' => str_repeat('q', 40),
        'expected_impact' => 'ok',
    ];

    expect(Validator::make([...$valid, $field => str_repeat('x', 5000)], IdeaRules::rules())->fails())->toBeFalse()
        ->and(Validator::make([...$valid, $field => str_repeat('x', 5001)], IdeaRules::rules())->errors()->has($field))->toBeTrue();
})->with(['problem', 'proposal', 'expected_impact']);
