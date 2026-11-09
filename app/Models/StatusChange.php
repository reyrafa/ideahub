<?php

namespace App\Models;

use App\Enums\IdeaStatus;
use Database\Factories\StatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $idea_id
 * @property IdeaStatus|null $from_status
 * @property IdeaStatus $to_status
 * @property string|null $note
 * @property int $changed_by
 * @property Carbon $changed_at
 */
#[Fillable(['idea_id', 'from_status', 'to_status', 'note', 'changed_by', 'changed_at'])]
class StatusChange extends Model
{
    /** @use HasFactory<StatusChangeFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => IdeaStatus::class,
            'to_status' => IdeaStatus::class,
            'changed_at' => 'datetime',
        ];
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
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
