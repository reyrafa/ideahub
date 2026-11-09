<?php

namespace App\Models;

use App\Enums\IdeaStatus;
use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $title
 * @property string $problem
 * @property string $proposal
 * @property string|null $expected_impact
 * @property bool $is_anonymous
 * @property IdeaStatus $status
 * @property int $votes_count
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id',
    'category_id',
    'title',
    'problem',
    'proposal',
    'expected_impact',
    'is_anonymous',
    'status',
    'submitted_at',
])]
class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdeaStatus::class,
            'is_anonymous' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return HasMany<StatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(StatusChange::class);
    }

    /**
     * The author's display name for the viewer (BR-7): anonymous ideas hide the
     * author from everyone except the author themself and users who may view it.
     */
    public function authorNameFor(?User $viewer): string
    {
        if (! $this->is_anonymous) {
            return $this->author->name;
        }

        if ($viewer !== null && ($viewer->id === $this->user_id || $viewer->can('viewAuthor', $this))) {
            return $this->author->name;
        }

        return 'Anonymous';
    }

    /**
     * Most votes first; ties go to the oldest submission.
     *
     * @param  Builder<Idea>  $query
     */
    #[Scope]
    protected function mostVoted(Builder $query): void
    {
        $query->orderByDesc('votes_count')->orderBy('submitted_at');
    }

    /**
     * @param  Builder<Idea>  $query
     */
    #[Scope]
    protected function newest(Builder $query): void
    {
        $query->orderByDesc('submitted_at');
    }
}
