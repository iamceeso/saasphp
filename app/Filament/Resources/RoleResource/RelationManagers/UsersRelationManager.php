<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Helpers\RoleHelper;
use App\Models\Role;
use App\Models\User;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make(name: 'name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make(name: 'phone'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->recordSelectOptionsQuery(fn() => User::query()->whereDoesntHave(
                        'roles',
                        fn($query) => $query->where('roles.id', $this->getOwnerRecord()->getKey())
                    ))
                    ->recordSelectSearchColumns(['name', 'email', 'phone'])
                    ->authorize(fn() => RoleHelper::canManageAssignments($this->getOwnerRecord()))
                    ->hidden(fn() => ! RoleHelper::canManageAssignments($this->getOwnerRecord()))
                    ->label('Attach User To Role'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->hidden(fn(User $record) => ! RoleHelper::canDetachRoleFromUser($this->getOwnerRecord(), $record))
                    ->authorize(fn(User $record) => RoleHelper::canDetachRoleFromUser($this->getOwnerRecord(), $record))
            ])
            ->bulkActions([
                // Tables\Actions\DetachBulkAction::make()
                //     ->label('Detach User From Role')
                //     ->disabled(
                //         fn($livewire) => strtolower($livewire->getOwnerRecord()->name) === 'admin' &&
                //             $livewire->getOwnerRecord()->users()->count() <= 1
                //     ),
            ]);
    }
}
