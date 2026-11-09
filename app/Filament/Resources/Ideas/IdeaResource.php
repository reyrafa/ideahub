<?php

namespace App\Filament\Resources\Ideas;

use App\Filament\Resources\Ideas\Pages\CreateIdea;
use App\Filament\Resources\Ideas\Pages\EditIdea;
use App\Filament\Resources\Ideas\Pages\ListIdeas;
use App\Filament\Resources\Ideas\Pages\ViewIdea;
use App\Filament\Resources\Ideas\Schemas\IdeaForm;
use App\Filament\Resources\Ideas\Schemas\IdeaInfolist;
use App\Filament\Resources\Ideas\Tables\IdeasTable;
use App\Models\Idea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IdeaResource extends Resource
{
    protected static ?string $model = Idea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    /**
     * Search ideas by their text only, never by author, so anonymous authors stay hidden (BR-7).
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'problem'];
    }

    public static function form(Schema $schema): Schema
    {
        return IdeaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IdeaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IdeasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIdeas::route('/'),
            'create' => CreateIdea::route('/create'),
            'view' => ViewIdea::route('/{record}'),
            'edit' => EditIdea::route('/{record}/edit'),
        ];
    }
}
