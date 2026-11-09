<x-filament-widgets::widget>
    <x-filament::section heading="AI weekly summary">
        @if ($summary)
            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                {{ $summary->period_start->format('M j, Y') }} to {{ $summary->period_end->format('M j, Y') }}
                ({{ $summary->ideas_count }} ideas summarized)
            </p>
            <div class="whitespace-pre-line text-sm">{{ $summary->summary }}</div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No weekly summary yet.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>