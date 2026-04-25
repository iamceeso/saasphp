<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Infolists\Components\RepeatableEntry;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', SubscriptionPlan::class) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('billing.enabled');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Plan Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true),
                        Select::make('cta_type')
                            ->label('Plan CTA')
                            ->options([
                                'subscribe' => 'Subscribe',
                                'contact' => 'Contact Sales',
                            ])
                            ->default('subscribe')
                            ->native(false)
                            ->reactive(),
                        TextInput::make('contact_button_text')
                            ->label('Contact button text')
                            ->placeholder('Contact Sales')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('cta_type') === 'contact'),
                        TextInput::make('contact_url')
                            ->label('Contact link')
                            ->placeholder('mailto:sales@example.com or /contact')
                            ->maxLength(255)
                            ->helperText('Used when this plan should lead to sales instead of checkout.')
                            ->required(fn (Forms\Get $get) => $get('cta_type') === 'contact')
                            ->visible(fn (Forms\Get $get) => $get('cta_type') === 'contact'),
                        Toggle::make('is_most_popular')
                            ->label('Most popular')
                            ->helperText('Marks this plan as the featured option on the pricing page.')
                            ->default(false),
                    ])
                    ->columns(2),
                Section::make('Pricing')
                    ->description('Define monthly and annual prices, including trial periods.')
                    ->schema([
                        Repeater::make('prices')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('interval')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'annually' => 'Annually',
                                    ])
                                    ->required()
                                    ->native(false),
                                TextInput::make('amount')
                                    ->numeric()
                                    ->step(1)
                                    ->minValue(0)
                                    ->required()
                                    ->helperText('Stored in minor units, e.g. 0 = free, 999 = $9.99'),
                                TextInput::make('trial_days')
                                    ->numeric()
                                    ->step(1)
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                                Toggle::make('is_active')
                                    ->default(true),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Features')
                    ->schema([
                        Repeater::make('features')
                            ->relationship()
                            ->schema([
                                TextInput::make('feature_key')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('feature_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('value')
                                    ->maxLength(255),
                                Textarea::make('description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('font-medium'),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('prices_count')
                    ->counts('prices')
                    ->label('Prices')
                    ->badge(),
                TextColumn::make('features_count')
                    ->counts('features')
                    ->label('Features')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                IconColumn::make('is_most_popular')
                    ->boolean()
                    ->label('Most Popular'),
                TextColumn::make('cta_type')
                    ->label('CTA')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'contact' ? 'Contact Sales' : 'Subscribe'),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (SubscriptionPlan $record) => auth()->user()?->can('update', $record)),
                Tables\Actions\Action::make('markMostPopular')
                    ->label('Make Most Popular')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (SubscriptionPlan $record) => $record->markAsMostPopular())
                    ->visible(fn (SubscriptionPlan $record) => ! $record->is_most_popular && (auth()->user()?->can('update', $record) ?? false)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SubscriptionPlan $record) => auth()->user()?->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
