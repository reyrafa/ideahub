<?php

namespace App\Console\Commands;

use App\Actions\GenerateWeeklySummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('ideas:summarize-week {--days=7 : Length of the period in days} {--dry-run : Print the exact payload that would be sent; no API call, nothing saved}')]
#[Description('F11: summarize the themes of the past week of ideas with the Claude API')]
class SummarizeWeek extends Command
{
    public function handle(GenerateWeeklySummary $action): int
    {
        $days = max(1, (int) $this->option('days'));

        if ($this->option('dry-run')) {
            $payload = $action->payload($days);

            $this->line($payload === null
                ? 'No new ideas in the period; nothing would be sent.'
                : (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $action->isConfigured()) {
            $this->warn('skipped: no ANTHROPIC_API_KEY configured.');

            return self::SUCCESS;
        }

        try {
            $summary = $action->handle($days);
        } catch (RuntimeException $e) {
            report($e);
            $this->error('Weekly summary failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($summary === null
            ? 'No new ideas in the period; nothing to summarize.'
            : "Summary saved for {$summary->ideas_count} ideas.");

        return self::SUCCESS;
    }
}
