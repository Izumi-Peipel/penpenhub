<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $modelLabel = 'Langganan';

    protected static ?string $pluralModelLabel = 'Langganan';

    protected static ?string $navigationGroup = 'Billing';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label('Perusahaan')
                ->relationship('company', 'name')
                ->searchable()
                ->required(),
            Forms\Components\Select::make('plan_id')
                ->label('Paket')
                ->relationship('plan', 'name')
                ->required(),
            Forms\Components\Select::make('billing_cycle')
                ->label('Siklus tagihan')
                ->options(['monthly' => 'Bulanan', 'yearly' => 'Tahunan'])
                ->default('monthly')
                ->required(),
            Forms\Components\DatePicker::make('starts_at')->label('Mulai')->required(),
            Forms\Components\DatePicker::make('ends_at')->label('Berakhir')->required(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(['active' => 'Aktif', 'expired' => 'Berakhir', 'cancelled' => 'Dibatalkan'])
                ->default('active')
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Paket')
                    ->badge(),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Siklus')
                    ->formatStateUsing(fn ($state) => $state === 'monthly' ? 'Bulanan' : 'Tahunan'),
                Tables\Columns\TextColumn::make('starts_at')->label('Mulai')->date('d M Y'),
                Tables\Columns\TextColumn::make('ends_at')->label('Berakhir')->date('d M Y'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'expired',
                        'warning' => 'cancelled',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit'   => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
