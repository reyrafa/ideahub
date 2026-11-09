<div>
    @forelse ($this->changes as $change)
        <div wire:key="change-{{ $change->id }}" style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200, #e5e7eb);">
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                @if ($change->from_status)
                    <x-filament::badge :color="$change->from_status->getColor()">{{ $change->from_status->getLabel() }}</x-filament::badge>
                    <span aria-hidden="true">&rarr;</span>
                @else
                    <span>Submitted as</span>
                @endif
                <x-filament::badge :color="$change->to_status->getColor()">{{ $change->to_status->getLabel() }}</x-filament::badge>
            </div>
            <div style="font-size: 0.875rem; color: var(--gray-500, #6b7280); margin-top: 0.25rem;">
                {{ $this->changedByName($change) }} &middot; {{ $change->changed_at->format('M j, Y H:i') }}
            </div>
            @if ($change->note)
                <p style="white-space: pre-line; margin-top: 0.25rem;">{{ $change->note }}</p>
            @endif
        </div>
    @empty
        <p style="color: var(--gray-500, #6b7280);">No status changes yet.</p>
    @endforelse
</div>