<?php

use App\Actions\ExportIdeas;
use App\Enums\IdeaStatus;
use App\Filament\Admin\Resources\Ideas\Pages\ListIdeas;
use App\Models\Category;
use App\Models\Idea;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

/**
 * Runs the export and parses it into header-keyed rows.
 *
 * @return array{raw: string, rows: array<int, array<string, string|null>>}
 */
function exportedCsv(User $user): array
{
    $raw = streamedBody(app(ExportIdeas::class)->handle($user));
    $lines = str_getcsv_all(substr($raw, 3));
    $header = array_shift($lines);

    return ['raw' => $raw, 'rows' => array_map(fn (array $line): array => array_combine($header, $line), $lines)];
}

function streamedBody(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * @return array<int, array<int, string|null>>
 */
function str_getcsv_all(string $csv): array
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csv);
    rewind($stream);

    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

it('F13 exports the expected header, a BOM and the dated filename', function () {
    Idea::factory()->create();

    $response = app(ExportIdeas::class)->handle($this->admin);
    $raw = streamedBody($response);

    expect($raw)->toStartWith("\xEF\xBB\xBF")
        ->and($response->headers->get('cache-control'))->toContain('no-store')->toContain('private')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($response->headers->get('content-disposition'))->toContain('ideas-'.now()->format('Ymd').'.csv')
        ->and(str_getcsv_all(substr($raw, 3))[0])->toBe([
            'id', 'title', 'category', 'status', 'votes_count', 'author', 'is_anonymous',
            'submitted_at', 'withdrawn_at', 'hours_saved', 'money_saved', 'results_recorded_at',
        ]);
});

it('F13 exports every idea including withdrawn ones, flagged by withdrawn_at', function () {
    $category = Category::factory()->create(['name' => 'Facilities']);
    $live = Idea::factory()->for($category)->create();
    $withdrawn = Idea::factory()->create();
    $withdrawn->delete();

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows)->toHaveCount(2)
        ->and($rows[$live->id]['category'])->toBe('Facilities')
        ->and($rows[$live->id]['status'])->toBe('New')
        ->and($rows[$live->id]['withdrawn_at'])->toBe('')
        ->and($rows[$live->id]['submitted_at'])->toBe($live->submitted_at->toIso8601String())
        ->and($rows[$withdrawn->id]['withdrawn_at'])->not->toBe('');
});

it('F13 exports the real vote row count', function () {
    $idea = Idea::factory()->create(['votes_count' => 99]);
    Vote::factory()->count(3)->for($idea)->create();
    $none = Idea::factory()->create();

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$idea->id]['votes_count'])->toBe('3')
        ->and($rows[$none->id]['votes_count'])->toBe('0');
});

it('F13 fills the results columns for Implemented ideas', function () {
    $implemented = Idea::factory()->implemented()->create(['hours_saved' => 12.5, 'money_saved' => 300]);
    $plain = Idea::factory()->create();

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$implemented->id]['status'])->toBe('Implemented')
        ->and($rows[$implemented->id]['hours_saved'])->toBe('12.5')
        ->and($rows[$implemented->id]['money_saved'])->toBe('300.00')
        ->and($rows[$implemented->id]['results_recorded_at'])->not->toBe('')
        ->and($rows[$plain->id]['hours_saved'])->toBe('')
        ->and($rows[$plain->id]['results_recorded_at'])->toBe('');
});

it('F13 shows the real author of an anonymous idea to the admin (BR-7)', function () {
    $author = User::factory()->employee()->create(['name' => 'Ada Lovelace']);
    $anonymous = Idea::factory()->anonymous()->for($author, 'author')->create();
    $named = Idea::factory()->for($author, 'author')->create();

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$anonymous->id]['author'])->toBe('Ada Lovelace')
        ->and($rows[$anonymous->id]['is_anonymous'])->toBe('yes')
        ->and($rows[$named->id]['is_anonymous'])->toBe('no');
});

it('F13 forbids everyone but admins from exporting', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect(fn () => app(ExportIdeas::class)->handle($user))->toThrow(AuthorizationException::class);
})->with(['employee', 'reviewer']);

it('F13 forbids a user without a role from exporting', function () {
    expect(fn () => app(ExportIdeas::class)->handle(User::factory()->create()))
        ->toThrow(AuthorizationException::class);
});

it('F13 hides the export action and blocks the admin list for employees and reviewers', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(ListIdeas::class)->assertForbidden();
})->with(['employee', 'reviewer']);

it('F13 redirects guests away from the admin ideas list', function () {
    $this->get('/admin/ideas')->assertRedirect();
});

it('F13 downloads the CSV through the Export CSV header action for an admin', function () {
    Idea::factory()->create(['title' => 'Better coffee']);
    $this->actingAs($this->admin);

    Livewire::test(ListIdeas::class)
        ->assertActionVisible('exportCsv')
        ->callAction('exportCsv')
        ->assertFileDownloaded('ideas-'.now()->format('Ymd').'.csv');
});

it('F13 prefixes formula-like cells with an apostrophe', function (string $title) {
    $idea = Idea::factory()->create(['title' => $title]);

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$idea->id]['title'])->toBe("'".$title);
})->with(['=SUM(1+1)', '+cmd', '-1', '@x', "\tTabbed", "\rReturn"]);

it('F13 prefixes formulas hidden behind leading whitespace or full-width characters', function (string $title) {
    $idea = Idea::factory()->create(['title' => $title]);

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$idea->id]['title'])->toBe("'".$title);
})->with([' =1+1', "\n=1+1", '  @x', "\u{FF1D}SUM(1)", "\u{FF0B}1", "\u{FF0D}1", "\u{FF20}x"]);

it('F13 leaves ordinary text untouched', function () {
    $idea = Idea::factory()->create(['title' => 'Plain = text']);

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$idea->id]['title'])->toBe('Plain = text');
});

it('F13 neutralizes formulas in the category and author cells too', function () {
    $idea = Idea::factory()
        ->for(Category::factory()->create(['name' => '=EVIL()']))
        ->for(User::factory()->create(['name' => '@attacker']), 'author')
        ->create();

    $row = collect(exportedCsv($this->admin)['rows'])->keyBy('id')[$idea->id];

    expect($row['category'])->toBe("'=EVIL()")->and($row['author'])->toBe("'@attacker");
});

it('F13 round-trips commas, quotes and newlines in titles', function () {
    $title = "He said \"hi\", then\nleft";
    $idea = Idea::factory()->create(['title' => $title]);

    $rows = collect(exportedCsv($this->admin)['rows'])->keyBy('id');

    expect($rows[$idea->id]['title'])->toBe($title)
        ->and($rows[$idea->id]['status'])->toBe(IdeaStatus::New->getLabel());
});
