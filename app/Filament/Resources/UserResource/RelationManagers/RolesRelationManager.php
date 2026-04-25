<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Helpers\RoleHelper;
use App\Models\Role;
use Filament\Actions\DetachAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    public function form(Schema $schema): Schema
    {
        return $schema
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
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Adding roles to user is done from the role dashboard
            ])
            ->recordActions([
                DetachAction::make()
                    ->hidden(fn (Role $record) => ! RoleHelper::canDetachRoleFromUser($record, $this->getOwnerRecord()))
                    ->authorize(fn (Role $record) => RoleHelper::canDetachRoleFromUser($record, $this->getOwnerRecord())),
            ])
            ->bulkActions([
                // No bulk actions available
            ]);
    }
}
