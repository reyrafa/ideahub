<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    /**
     * Assignable roles (built-in and custom), keyed by role name.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => Str::headline($name)])
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('department')
                    ->maxLength(255),
                Select::make('role')
                    ->options(fn (): array => self::roleOptions())
                    ->required()
                    ->in(fn (): array => array_keys(self::roleOptions()))
                    ->selectablePlaceholder(false)
                    ->default('employee')
                    ->afterStateHydrated(function (Select $component, ?User $record): void {
                        if ($record !== null) {
                            $component->state($record->roles()->value('name') ?? 'employee');
                        }
                    }),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->maxLength(255)
                    ->visibleOn('create'),
            ]);
    }
}
