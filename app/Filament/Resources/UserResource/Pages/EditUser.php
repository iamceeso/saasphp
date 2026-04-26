<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if (! $record) {
            return false;
        }

        if (! $record instanceof User) {
            $record = User::find($record);
        }

        return $record ? auth()->user()?->can('update', $record) : false;
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->color('success')
                ->label('')
                ->visible(
                    fn (User $record) => auth()->user()?->can('delete', $record) &&
                        ! $record->isSuperAdmin()
                ),
            Actions\RestoreAction::make()
                ->icon('heroicon-o-arrow-path')
                ->label('')->color('warning')
                ->visible(
                    fn (User $record) => auth()->user()?->can('restore', $record) && $record->trashed()
                ),
            Actions\ForceDeleteAction::make()
                ->icon('heroicon-o-trash')
                ->label('')->color('danger')
                ->visible(
                    fn (User $record) => auth()->user()?->can('forceDelete', $record) &&
                        ! $record->isSuperAdmin()
                ),
        ];

        $actions[] = Impersonate::make()
            ->record($this->record)
            ->visible(
                fn () => auth()->user()?->can('impersonate', User::class) &&
                    $this->record->isStandardUser() &&
                    (! ($this->record->phone && ! $this->record->hasVerifiedPhone())) &&
                    (! ($this->record->email && ! $this->record->hasVerifiedEmail()))
            );

        return $actions;
    }
}
