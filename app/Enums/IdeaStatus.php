<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IdeaStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Implemented = 'implemented';

    /**
     * The statuses an idea in this status may move to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::UnderReview],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved => [self::Implemented],
            self::Rejected, self::Implemented => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Whether moving an idea into this status requires a reviewer note.
     */
    public function requiresNote(): bool
    {
        return in_array($this, [self::Rejected, self::Implemented], true);
    }

    public function isVotingOpen(): bool
    {
        return in_array($this, [self::New, self::UnderReview], true);
    }

    public function allowsComments(): bool
    {
        return $this !== self::Rejected;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::UnderReview => 'Under review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Implemented => 'Implemented',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::UnderReview => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Implemented => 'success',
        };
    }
}
