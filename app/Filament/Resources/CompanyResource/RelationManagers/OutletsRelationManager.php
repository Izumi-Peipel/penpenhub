<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Support\OperatingHours;
use App\Support\OperatingHoursRows;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OutletsRelationManager extends RelationManager
{
    protected static string $relationship = 'outlets';

    protected static ?string $title = 'Outlet';

    public function form(Form $form): Form
    {
        $zones = timezone_identifiers_list();

        return $form->schema([
            Forms\Components\Select::make('brand_id')->label('Brand')
                ->options(fn () => $this->getOwnerRecord()->brands()->pluck('name', 'id'))
                ->required(),
            Forms\Components\TextInput::make('name')->label('Nama outlet')->required()->maxLength(120),
            Forms\Components\Textarea::make('address')->label('Alamat')->maxLength(500),
            Forms\Components\Select::make('timezone')->label('Zona waktu')
                ->options(array_combine($zones, $zones))->searchable()->default('Asia/Jakarta')->required(),
            Forms\Components\Repeater::make('operating_hours')->label('Jam operasional')
                ->helperText('Jam tutup lebih kecil dari jam buka berarti melewati tengah malam. Tutup 00:00 = sampai tengah malam.')
                ->schema([
                    Forms\Components\Select::make('day')->label('Hari')->options(OperatingHoursRows::dayLabels())->required(),
                    Forms\Components\TimePicker::make('open')->label('Buka')->seconds(false)->format('H:i')->required(),
                    Forms\Components\TimePicker::make('close')->label('Tutup')->seconds(false)->format('H:i')->required(),
                ])
                ->columns(3)
                ->defaultItems(0)
                ->minItems(1)
                ->formatStateUsing(fn ($state) => OperatingHoursRows::toRows($state))
                ->dehydrateStateUsing(fn ($state) => OperatingHoursRows::fromRows($state))
                ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                    foreach (OperatingHours::errors(OperatingHoursRows::fromRows($value)) as $message) {
                        $fail($message);
                    }
                })
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Outlet')->searchable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand'),
                Tables\Columns\TextColumn::make('timezone')->label('Zona waktu'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
