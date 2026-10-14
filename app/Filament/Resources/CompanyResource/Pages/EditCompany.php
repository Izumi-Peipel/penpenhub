<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Actions\InviteUser;
use App\Filament\Resources\CompanyResource;
use App\Models\Brand;
use App\Models\Outlet;
use App\Support\Roles;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invite')
                ->label('Undang pengguna')
                ->icon('heroicon-o-envelope')
                ->form([
                    Forms\Components\TextInput::make('email')->email()->required(),
                    Forms\Components\Select::make('role')->label('Role')
                        ->options(collect(Roles::MEMBERSHIP)->mapWithKeys(fn ($r) => [$r => Roles::label($r)])->all())
                        ->required()->live(),
                    Forms\Components\Select::make('brand_id')->label('Brand')
                        ->options(fn () => Brand::query()->where('company_id', $this->record->id)->pluck('name', 'id'))
                        ->visible(fn (Get $get): bool => $get('role') === Roles::BRAND_MANAGER)
                        ->required(fn (Get $get): bool => $get('role') === Roles::BRAND_MANAGER),
                    Forms\Components\Select::make('outlet_id')->label('Outlet')
                        ->options(fn () => Outlet::query()->where('company_id', $this->record->id)->pluck('name', 'id'))
                        ->visible(fn (Get $get): bool => $get('role') === Roles::OUTLET_MANAGER)
                        ->required(fn (Get $get): bool => $get('role') === Roles::OUTLET_MANAGER),
                ])
                ->action(function (array $data): void {
                    $scopeId = match (Roles::scopeTypeFor($data['role'])) {
                        Roles::SCOPE_BRAND => $data['brand_id'] ?? null,
                        Roles::SCOPE_OUTLET => $data['outlet_id'] ?? null,
                        default => null,
                    };

                    try {
                        app(InviteUser::class)->handle(
                            auth()->user(),
                            $this->record,
                            $data['email'],
                            $data['role'],
                            $scopeId ? (int) $scopeId : null,
                        );
                        Notification::make()->title('Undangan dikirim')->success()->send();
                    } catch (ValidationException|AuthorizationException $e) {
                        Notification::make()->title('Undangan gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
