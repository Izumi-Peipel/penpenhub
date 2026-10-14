<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Paket';

    protected static ?string $pluralModelLabel = 'Paket';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(32)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(80),
            Forms\Components\TextInput::make('price_monthly')->label('Harga/bulan (ex-PPN)')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('price_yearly')->label('Harga/tahun (ex-PPN)')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('hours_quota')->label('Kuota jam/bulan')->numeric()->required(),
            Forms\Components\TextInput::make('request_quota')->label('Kuota permintaan/bulan')->numeric()->required(),
            Forms\Components\TextInput::make('addon_hour_price')->label('Add-on per jam')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('addon_request_price')->label('Add-on per permintaan')->numeric()->prefix('Rp')->required(),
            Forms\Components\TextInput::make('uptime_monitor_limit')->label('Batas monitor uptime')->numeric()->required(),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Paket')->searchable(),
                Tables\Columns\TextColumn::make('price_monthly')->label('Per bulan')->money('IDR'),
                Tables\Columns\TextColumn::make('hours_quota')->label('Jam'),
                Tables\Columns\TextColumn::make('request_quota')->label('Permintaan'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
