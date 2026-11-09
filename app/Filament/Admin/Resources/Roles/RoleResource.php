<?php

namespace App\Filament\Admin\Resources\Roles;

use App\Actions\SaveRole;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Override;
use Spatie\Permission\Models\Role;

/**
 * Published replacement for Shield's role resource (Shield skips its own when a
 * *\RoleResource is registered on the panel). It edits only the seeded permission
 * catalogue in SaveRole::CATALOG instead of generating per-resource permissions.
 */
class RoleResource extends ShieldRoleResource
{
    #[Override]
    public static function form(Schema $schema): Schema
    {
        $groups = [];

        foreach (SaveRole::CATALOG as $group => $permissions) {
            $groups[] = Section::make($group)
                ->compact()
                ->schema([
                    CheckboxList::make('granted.'.$group)
                        ->hiddenLabel()
                        ->options($permissions)
                        ->columns(2),
                ]);
        }

        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('guard_name', 'web'))
                        ->disabled(fn (?Role $record): bool => $record !== null && in_array($record->name, SaveRole::BUILT_IN, true))
                        ->helperText(fn (?Role $record): ?string => $record !== null && in_array($record->name, SaveRole::BUILT_IN, true)
                            ? 'Built-in roles cannot be renamed.'
                            : null),
                ])
                ->columnSpanFull(),
            ...$groups,
        ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->badge()
                    ->counts('permissions'),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
