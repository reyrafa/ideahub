<?php

namespace App\Filament\Resources\Ideas\Schemas;

use App\Livewire\IdeaComments;
use App\Livewire\IdeaStatusHistory;
use App\Models\Idea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IdeaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('category.name')
                                    ->label('Category'),
                                TextEntry::make('author_name')
                                    ->label('Author')
                                    ->state(fn (Idea $record): string => self::authorLabel($record)),
                                TextEntry::make('submitted_at')
                                    ->label('Submitted')
                                    ->dateTime('M j, Y'),
                                TextEntry::make('votes_count')
                                    ->label('Votes')
                                    ->numeric(),
                            ]),
                        TextEntry::make('problem')
                            ->label('Problem')
                            ->columnSpanFull(),
                        TextEntry::make('proposal')
                            ->label('Proposal')
                            ->columnSpanFull(),
                        TextEntry::make('expected_impact')
                            ->label('Expected impact')
                            ->placeholder('Not provided')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Comments')
                    ->schema([
                        Livewire::make(IdeaComments::class, fn (Idea $record): array => ['idea' => $record]),
                    ])
                    ->columnSpanFull(),
                Section::make('Status history')
                    ->schema([
                        Livewire::make(IdeaStatusHistory::class, fn (Idea $record): array => ['idea' => $record]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The author's name for the viewer (BR-7). Viewers who may see through anonymity
     * (the author and admins) get the real name flagged as anonymous.
     */
    private static function authorLabel(Idea $idea): string
    {
        $name = $idea->authorNameFor(auth()->user());

        if ($idea->is_anonymous && $name !== 'Anonymous') {
            return "{$name} (anonymous)";
        }

        return $name;
    }
}
