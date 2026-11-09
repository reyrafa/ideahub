<?php

namespace App\Actions;

use App\Models\Idea;
use App\Models\WeeklySummary;
use App\Support\WeeklySummaryPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * F11: summarizes the themes of the last N days of ideas with the Claude API.
 *
 * Only {@see WeeklySummaryPayload} decides what is sent. Nothing is stored unless the
 * API answered successfully with non-empty text.
 */
class GenerateWeeklySummary
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(private readonly WeeklySummaryPayload $payloadBuilder) {}

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * The exact request body that would be sent, or null when the period has no ideas.
     *
     * @return array<string, mixed>|null
     */
    public function payload(int $days = 7, ?CarbonImmutable $now = null): ?array
    {
        $ideas = $this->ideas($days, $now ?? CarbonImmutable::now());

        if ($ideas->isEmpty()) {
            return null;
        }

        return $this->payloadBuilder->build($ideas, $this->model());
    }

    /**
     * @throws RuntimeException when the API call fails or returns no usable text.
     */
    public function handle(int $days = 7, ?CarbonImmutable $now = null): ?WeeklySummary
    {
        $now ??= CarbonImmutable::now();
        $ideas = $this->ideas($days, $now);

        if ($ideas->isEmpty()) {
            return null;
        }

        $text = $this->requestSummary($this->payloadBuilder->build($ideas, $this->model()));

        return WeeklySummary::query()->updateOrCreate(
            [
                'period_start' => $now->subDays($days)->toDateString(),
                'period_end' => $now->toDateString(),
            ],
            [
                'summary' => $text,
                // Number of ideas actually summarized (capped at MAX_IDEAS), not the number submitted.
                'ideas_count' => $ideas->count(),
                'model' => $this->model(),
            ],
        );
    }

    /**
     * @return Collection<int, Idea>
     */
    private function ideas(int $days, CarbonImmutable $now): Collection
    {
        return Idea::query()
            ->with('category:id,name')
            ->whereBetween('submitted_at', [$now->subDays($days), $now])
            ->newest()
            ->limit(WeeklySummaryPayload::MAX_IDEAS)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestSummary(array $payload): string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => (string) config('services.anthropic.version'),
            ])
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout((int) config('services.anthropic.timeout'))
                ->retry(2, 1000, fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException
                        && ($e->response->serverError() || $e->response->status() === 429)), throw: true)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, $payload)
                ->throw();
        } catch (Throwable $e) {
            throw new RuntimeException('Claude API request failed: '.$e::class, previous: $e);
        }

        $blocks = $response->json('content');
        $parts = [];

        foreach (is_array($blocks) ? $blocks : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        $text = trim(implode("\n", $parts));
        if ($text === '') {
            throw new RuntimeException('Claude API returned no text content.');
        }

        return $text;
    }

    private function model(): string
    {
        return (string) config('services.anthropic.model');
    }
}
