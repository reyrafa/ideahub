<?php

namespace App\Filament\Admin\Resources\Ideas;

use App\Filament\Admin\Resources\Ideas\Pages\ListIdeas;
use App\Filament\Admin\Resources\Ideas\Tables\IdeasTable;
use App\Models\Idea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Admin-only view of withdrawn (soft-deleted) ideas, with restore. Reviewing and voting stay in /app.
 */
class IdeaResource extends Resource
{
    protected static ?string $model = Idea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('ideas.manage-any') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return IdeasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIdeas::route('/'),
        ];
    }
}
