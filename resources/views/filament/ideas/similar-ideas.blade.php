@php
    use App\Filament\Resources\Ideas\IdeaResource;
@endphp

<div>
    <p style="margin-bottom: 0.5rem;">
        These ideas look similar. Please vote for or comment on one of them instead of posting a duplicate.
    </p>
    <ul style="display: flex; flex-direction: column; gap: 0.5rem;">
        @foreach ($ideas as $idea)
            <li wire:key="similar-idea-{{ $idea->id }}" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <a href="{{ IdeaResource::getUrl('view', ['record' => $idea]) }}" target="_blank" rel="noopener" class="fi-link" style="font-weight: 500;">{{ $idea->title }}</a>
                <x-filament::badge :color="$idea->status->getColor()">{{ $idea->status->getLabel() }}</x-filament::badge>
                <span style="font-size: 0.875rem; color: var(--gray-500, #6b7280);">{{ trans_choice(':count vote|:count votes', $idea->votes_count) }}</span>
            </li>
        @endforeach
    </ul>
</div>
