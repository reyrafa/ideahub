<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    /**
     * Assignable roles, keyed by role name.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        'employee' => 'Employee',
        'reviewer' => 'Reviewer',
        'admin' => 'Admin',
    ];

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
                    ->options(self::ROLES)
                    ->required()
                    ->in(array_keys(self::ROLES))
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
