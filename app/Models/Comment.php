<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $idea_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_hidden
 * @property int|null $hidden_by
 * @property Carbon|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['idea_id', 'user_id', 'body', 'is_hidden', 'hidden_by', 'hidden_at'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * Hide hidden comments from users without the permission to see them (BR-14).
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->can('comments.view-hidden')) {
            $query->where('is_hidden', false);
        }
    }

    /**
     * The commenter's display name for the viewer; the anonymous idea author
     * stays anonymous in their own comments too (BR-7).
     */
    public function authorNameFor(?User $viewer): string
    {
        if ($this->user_id === $this->idea->user_id) {
            return $this->idea->authorNameFor($viewer);
        }

        return $this->author->name;
    }

    /**
     * @return BelongsTo<Idea, $this>
     */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
