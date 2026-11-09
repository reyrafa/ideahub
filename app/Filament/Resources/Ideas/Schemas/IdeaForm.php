<?php

namespace App\Filament\Resources\Ideas\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Field constraints mirror App\Rules\IdeaRules (BR-5); the action re-validates server-side.
 */
class IdeaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->minLength(10)
                    ->maxLength(120)
                    ->columnSpanFull(),
                Select::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => Category::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->native(false)
                    ->columnSpanFull(),
                Textarea::make('problem')
                    ->label('Problem')
                    ->helperText('What is going wrong today? At least 30 characters.')
                    ->required()
                    ->minLength(30)
                    ->rows(5)
                    ->columnSpanFull(),
                Textarea::make('proposal')
                    ->label('Proposal')
                    ->helperText('What should we do about it? At least 30 characters.')
                    ->required()
                    ->minLength(30)
                    ->rows(5)
                    ->columnSpanFull(),
                Textarea::make('expected_impact')
                    ->label('Expected impact')
                    ->rows(3)
                    ->columnSpanFull(),
                Checkbox::make('is_anonymous')
                    ->label('Submit anonymously')
                    ->helperText('Employees and reviewers will see "Anonymous" instead of your name.')
                    ->columnSpanFull(),
            ]);
    }
}
