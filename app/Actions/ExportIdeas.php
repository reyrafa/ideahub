<?php

namespace App\Actions;

use App\Models\Idea;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F13: streams every idea, withdrawn ones included, as a CSV download.
 *
 * Admin only (ideas.manage-any), so the real author is exported even for anonymous
 * ideas, which BR-7 allows because only admins can see who wrote them.
 */
class ExportIdeas
{
    private const CHUNK_SIZE = 500;

    /**
     * Full-width = + - @ as UTF-8 byte sequences.
     *
     * @var array<int, string>
     */
    private const FULL_WIDTH_STARTERS = ["\xEF\xBC\x9D", "\xEF\xBC\x8B", "\xEF\xBC\x8D", "\xEF\xBC\xA0"];

    /**
     * @var array<int, string>
     */
    private const HEADER = [
        'id',
        'title',
        'category',
        'status',
        'votes_count',
        'author',
        'is_anonymous',
        'submitted_at',
        'withdrawn_at',
        'hours_saved',
        'money_saved',
        'results_recorded_at',
    ];

    public function handle(User $user): StreamedResponse
    {
        Gate::forUser($user)->authorize('export', Idea::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADER, escape: '');

            Idea::query()
                ->withTrashed()
                ->with(['author', 'category'])
                ->withCount(['votes as vote_rows_count'])
                ->chunkById(self::CHUNK_SIZE, function ($ideas) use ($handle): void {
                    /** @var Idea $idea */
                    foreach ($ideas as $idea) {
                        fputcsv($handle, $this->row($idea), escape: '');
                    }
                });

            fclose($handle);
        }, 'ideas-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The votes column is the real Vote row count, which equals ideas.votes_count by BR-4.
     *
     * @return array<int, string|int|null>
     */
    private function row(Idea $idea): array
    {
        /** @var int $voteRows */
        $voteRows = $idea->getAttribute('vote_rows_count');

        return array_map(self::neutralizeFormula(...), [
            $idea->id,
            $idea->title,
            $idea->category->name,
            $idea->status->getLabel(),
            $voteRows,
            $idea->author->name,
            $idea->is_anonymous ? 'yes' : 'no',
            $this->date($idea->submitted_at),
            $this->date($idea->deleted_at),
            $idea->hours_saved,
            $idea->money_saved,
            $this->date($idea->results_recorded_at),
        ]);
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    /**
     * Stops spreadsheet apps from running a cell as a formula (CSV injection).
     */
    private static function neutralizeFormula(string|int|null $value): string|int|null
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return (str_contains("\t\r\n", $value[0]) || self::startsWithFormulaCharacter(ltrim($value, " \t\r\n\0")))
            ? "'".$value
            : $value;
    }

    /**
     * ASCII and full-width (U+FF1D, U+FF0B, U+FF0D, U+FF20) formula starters.
     */
    private static function startsWithFormulaCharacter(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (str_contains('=+-@', $value[0])) {
            return true;
        }

        foreach (self::FULL_WIDTH_STARTERS as $starter) {
            if (str_starts_with($value, $starter)) {
                return true;
            }
        }

        return false;
    }
}
