<?php

namespace App\Models;

use Database\Factories\WeeklySummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * F11: the AI-written themes summary for one period. One row per period.
 *
 * @property int $id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $summary
 * @property int $ideas_count Ideas summarized (at most WeeklySummaryPayload::MAX_IDEAS), not all ideas submitted
 * @property string $model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['period_start', 'period_end', 'summary', 'ideas_count', 'model'])]
class WeeklySummary extends Model
{
    /** @use HasFactory<WeeklySummaryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'ideas_count' => 'integer',
        ];
    }
}
