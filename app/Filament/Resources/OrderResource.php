<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Repeater;
use Auth;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;



class OrderResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Services';
    protected static ?int $navigationSort = -3;



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Card::make()
                ->schema([
                Forms\Components\TextInput::make('name')
                    ->readOnly()
                    ->default(Auth::user()->name)
                    ->maxLength(255),
                Forms\Components\TextInput::make('alamat')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('catatan')
                    ->nullable()
                    ->columnSpanFull(),
                ])->columns(3),
                
                // Repeater
                Card::make('services')
                ->schema([
                    self::getItemsRepeater(),
                ]),

                Forms\Components\TextInput::make('brand_ac')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('teknisi_id')
                    ->relationship('teknisi', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('jadwal_kunjungan')
                    ->required(),
                Forms\Components\TextInput::make('total_price')
                    ->required()
                    ->prefix('Rp.')
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        'ordering' => 'Ordering',
                        'prosessing' => 'Prosessing',
                        'complete' => 'Complete',
                    ])
                    ->default('ordering')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pelanggan')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('alamat')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('brand_ac')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('teknisi.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('teknisi.phone')
                    ->label('Teknisi Phone')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jadwal_kunjungan')
                    ->date('l, d F Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->prefix('Rp.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ordering' => 'primary',
                        'prosessing' => 'warning',
                        'complete' => 'success',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getItemsRepeater() : Repeater
    {
        return Repeater::make('items')
        ->relationship()
        ->schema([
            Forms\Components\Select::make('service_id')
            ->label('Services')
            ->options(Service::query()->pluck('title', 'id'))
            ->required()
            ->reactive()
            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                $service = Service::find($state);
                $set('unit_price', $service?->price ?? 0);
                $quantity = $get('quantity') ?? 1; //Get quantity or default to 1
                self::updateTotalPrice($get, $set); 

            })
            ->distinct()
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
            ->columnSpan([
                'md' => 6
            ]),
            Forms\Components\TextInput::make('quantity')
            ->label('Quantity')
            ->numeric()
            ->default(1)
            ->columnSpan([
                'md' => 2
            ])
            ->required()
            ->reactive()
            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::updateTotalPrice($get, $set)), 
            
            Forms\Components\TextInput::make('unit_price')
            ->numeric()
            ->disabled()
            ->prefix('Rp.')
            ->dehydrated()
            ->required()
            ->columnSpan([
                'md' => 2
            ]),
        ])
        ->defaultItems(1)
        ->hiddenLabel()
        ->columns([
            'md' => 10,
        ])
        ->live()
        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
            self::updateTotalPrice($get, $set);
        });
    }

    protected static function updateTotalPrice(Forms\Get $get, Forms\set $set): void
    {
        $selectedServices = collect($get('items'))->filter(fn($item) => !empty($item['service_id']) && !empty($item['quantity']));

        $prices = Service::find($selectedServices->pluck('service_id'))->pluck('price','id');
        $total = $selectedServices->reduce(function ($total, $service) use ($prices) {
            return $total + ($prices[$service['service_id']] * $service['quantity']);
        },0);

        $set('total_price', $total);
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }
}
