<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Models\Outlet;
use App\Support\AssetTypes;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Aset digital';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('brand_id')->label('Brand')
                ->options(fn () => $this->getOwnerRecord()->brands()->pluck('name', 'id'))
                ->required()->live(),
            Forms\Components\Select::make('outlet_id')->label('Outlet (kosong = level brand)')
                ->options(fn (Get $get) => Outlet::query()->where('brand_id', $get('brand_id'))->pluck('name', 'id'))
                ->nullable(),
            Forms\Components\Select::make('type')->label('Jenis')->options(AssetTypes::labels())->required()->live(),
            Forms\Components\TextInput::make('name')->label('Nama aset')->required()->maxLength(120),
            Forms\Components\TextInput::make('endpoint_url')->label('URL (http/https)')
                ->rules(['url:http,https'])->maxLength(2048)
                ->visible(fn (Get $get): bool => $get('type') !== AssetTypes::POS),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Aset')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('Jenis')
                    ->formatStateUsing(fn (string $state) => AssetTypes::labels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand'),
                Tables\Columns\TextColumn::make('outlet.name')->label('Outlet')->placeholder('Level brand'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
