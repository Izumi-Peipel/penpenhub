<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Outlet;
use App\Support\Roles;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Akses (role per perusahaan)';

    public function form(Form $form): Form
    {
        $needsScope = fn (Get $get): bool => in_array(Roles::scopeTypeFor((string) $get('role')), [Roles::SCOPE_BRAND, Roles::SCOPE_OUTLET], true);

        return $form->schema([
            Forms\Components\Select::make('company_id')->label('Perusahaan')
                ->options(fn () => Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                ->required()->live(),
            Forms\Components\Select::make('role')->label('Role')
                ->options(collect(Roles::MEMBERSHIP)->mapWithKeys(fn ($r) => [$r => Roles::label($r)])->all())
                ->required()->live(),
            Forms\Components\Select::make('scope_id')->label('Brand/Outlet')
                ->options(function (Get $get) {
                    $companyId = $get('company_id');

                    return match (Roles::scopeTypeFor((string) $get('role'))) {
                        Roles::SCOPE_BRAND => Brand::withoutGlobalScopes()->where('company_id', $companyId)->pluck('name', 'id'),
                        Roles::SCOPE_OUTLET => Outlet::withoutGlobalScopes()->where('company_id', $companyId)->pluck('name', 'id'),
                        default => [],
                    };
                })
                ->visible($needsScope)
                ->required($needsScope),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')->label('Perusahaan'),
                Tables\Columns\TextColumn::make('role')->formatStateUsing(fn (string $state) => Roles::label($state)),
                Tables\Columns\TextColumn::make('scope_type')->label('Scope'),
                Tables\Columns\TextColumn::make('scope_id')->label('ID scope'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->mutateFormDataUsing(function (array $data): array {
                    $data['scope_type'] = Roles::scopeTypeFor($data['role']);

                    if ($data['scope_type'] === Roles::SCOPE_COMPANY) {
                        $data['scope_id'] = (int) $data['company_id'];
                    }

                    return $data;
                }),
            ])
            ->actions([Tables\Actions\DeleteAction::make()]);
    }
}
