<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerSubscriptionResource\Pages;
use App\Models\CustomerSubscription;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class CustomerSubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', CustomerSubscription::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('billing.enabled');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Subscription')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'trialing' => 'Trialing',
                                'active' => 'Active',
                                'past_due' => 'Past Due',
                                'canceled' => 'Canceled',
                                'unpaid' => 'Unpaid',
                                'incomplete' => 'Incomplete',
                                'incomplete_expired' => 'Incomplete Expired',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('interval')
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
                            ->required(),
                        DateTimePicker::make('current_period_start')
                            ->required(),
                        DateTimePicker::make('current_period_end')
                            ->required(),
                        DateTimePicker::make('trial_ends_at'),
                        DateTimePicker::make('canceled_at'),
                        DateTimePicker::make('ended_at'),
                    ])
                    ->columns(2),
                Section::make('Provider References')
                    ->schema([
                        TextInput::make('stripe_subscription_id')
                            ->disabled(),
                        TextInput::make('stripe_customer_id')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        'past_due' => 'warning',
                        'canceled' => 'danger',
                        'unpaid', 'incomplete', 'incomplete_expired' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('interval')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('amount')
                    ->money('USD', divideBy: 100)
                    ->label('Amount'),
                TextColumn::make('trial_ends_at')
                    ->dateTime()
                    ->label('Trial Ends'),
                TextColumn::make('canceled_at')
                    ->dateTime()
                    ->label('Canceled At')
                    ->toggleable(),
                TextColumn::make('current_period_end')
                    ->dateTime()
                    ->label('Renews / Ends'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'trialing' => 'Trialing',
                        'active' => 'Active',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                        'unpaid' => 'Unpaid',
                        'incomplete' => 'Incomplete',
                        'incomplete_expired' => 'Incomplete Expired',
                    ]),
                SelectFilter::make('interval')
                    ->options([
                        'monthly' => 'Monthly',
                        'annually' => 'Annually',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerSubscriptions::route('/'),
            'view' => Pages\ViewCustomerSubscription::route('/{record}'),
            'edit' => Pages\EditCustomerSubscription::route('/{record}/edit'),
        ];
    }
}
