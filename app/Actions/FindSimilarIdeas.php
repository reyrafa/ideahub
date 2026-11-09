<?php

namespace App\Actions;

use App\Models\Idea;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F9: finds existing ideas whose titles share significant words with a draft title.
 *
 * Soft-deleted ideas never match (SoftDeletes scope). The caller only ever shows
 * the idea itself, never its author, so BR-7 holds.
 */
class FindSimilarIdeas
{
    private const MIN_WORD_LENGTH = 4;

    private const CANDIDATE_LIMIT = 200;

    /**
     * Words that carry no meaning on their own and would match unrelated titles.
     *
     * @var array<int, string>
     */
    private const STOP_WORDS = [
        'about', 'after', 'again', 'also', 'because', 'been', 'before', 'being', 'between', 'could',
        'does', 'doing', 'down', 'each', 'even', 'every', 'from', 'have', 'having', 'here', 'into',
        'just', 'like', 'make', 'many', 'more', 'most', 'much', 'must', 'only', 'other', 'over',
        'same', 'should', 'some', 'such', 'than', 'that', 'their', 'them', 'then', 'there', 'these',
        'they', 'this', 'those', 'through', 'under', 'used', 'using', 'very', 'want', 'were', 'what',
        'when', 'where', 'which', 'while', 'will', 'with', 'within', 'without', 'would', 'your',
        'idea', 'ideas', 'need', 'needs', 'better', 'improve', 'add',
    ];

    /**
     * @return Collection<int, Idea>
     */
    public function handle(?string $title, ?User $viewer, ?int $excludeIdeaId = null, int $limit = 5): Collection
    {
        $words = $this->significantWords($title ?? '');

        if ($viewer === null || $words === []) {
            return new Collection;
        }

        $required = min(2, count($words));

        return Idea::query()
            ->when($excludeIdeaId !== null, fn ($query) => $query->whereKeyNot($excludeIdeaId))
            ->where(function ($query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhere('title', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            })
            ->orderByDesc('votes_count')
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->map(fn (Idea $idea): array => [
                'idea' => $idea,
                'shared' => count(array_intersect($words, $this->significantWords($idea->title))),
            ])
            ->filter(fn (array $match): bool => $match['shared'] >= $required)
            ->filter(fn (array $match): bool => $viewer->can('view', $match['idea']))
            ->sort(fn (array $a, array $b): int => [$b['shared'], $b['idea']->votes_count] <=> [$a['shared'], $a['idea']->votes_count])
            ->take($limit)
            ->map(fn (array $match): Idea => $match['idea'])
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function significantWords(string $title): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH && ! in_array($word, self::STOP_WORDS, true),
        )));
    }
}
