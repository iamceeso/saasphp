<?php

namespace App\Filament\Pages;

use App\Events\ImageUpdated;
use App\Models\Setting;
use DateTimeZone;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Class SiteSettings
 *
 * This Filament page handles application-wide settings stored in the `settings` table.
 * Settings are grouped into tabs and sections including Site Info, Social Login, Features,
 * Email Clients, SMS Clients, and Payment Gateways.
 */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms, WithFileUploads;

    /**
     * Form data state.
     */
    public array $data = [];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.site-settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 9999;

    /** this flag drives your button-disabled checks */
    public bool $uploading = false;

    /**
     * Check user permission to access the page.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('modify', Setting::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('message.configuration_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('message.site_settings_title');
    }

    /**
     * Initialize settings form with values from database.
     */
    public function mount(): void
    {
        $payload = [];

        foreach (Setting::all() as $setting) {
            $decrypted = $setting->value;

            // booleans need to be cast back
            if ($setting->type === 'boolean') {
                $decrypted = filter_var($decrypted, FILTER_VALIDATE_BOOLEAN);
            }

            Arr::set($payload, $setting->key, $decrypted);
        }

        // fill Filament’s form
        $this->form->fill([
            'data' => $payload,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Tabs::make('SettingsTabs')
                ->statePath('data')
                ->tabs([
                    Tabs\Tab::make('Site')->label(__('message.site_tab'))
                        ->schema([
                            Section::make('Information')
                                ->collapsible()
                                ->icon('heroicon-o-information-circle')
                                ->collapsed()
                                ->columns(2)
                                ->schema([
                                    TextInput::make('site.name')
                                        ->required()
                                        ->rules(['required', 'max:255'])
                                        ->label(__('message.site_name')),

                                    TextInput::make('site.url')
                                        ->rules([
                                            'required',
                                            'max:255',
                                            'regex:/^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/',
                                            'not_regex:/http[s]?:\/\//',
                                            'not_regex:/\//',
                                        ])
                                        ->placeholder('saasphp.com')
                                        ->label(__('message.site_url')),

                                    TextInput::make('site.description')
                                        ->required()
                                        ->rules(['required', 'max:255'])
                                        ->placeholder('A brief description of your site')
                                        ->label(__('message.site_description'))
                                        ->columnSpanFull(),
                                ])->label(_('message.information_section')),
                            Section::make('Logo')
                                ->collapsible()
                                ->icon('heroicon-o-camera')
                                ->collapsed()
                                ->columns(2)
                                ->schema([
                                    FileUpload::make('site.logo')
                                        ->uploadingMessage('Uploading logo...')
                                        ->disk('public')
                                        ->previewable()
                                        ->directory('logos')
                                        ->visibility('public')
                                        ->multiple(false)
                                        ->label(__('message.site_logo'))
                                        ->maxSize(1024)
                                        ->image()
                                        ->live()
                                        ->imageEditor()
                                        ->imageEditorAspectRatios([
                                            '16:9',
                                            '4:3',
                                            '1:1',
                                        ])
                                        ->reactive()
                                        ->nullable()
                                        ->columnSpanFull(),
                                ])->label(_('message.logo_section')),
                            Section::make('Miscellaneous')
                                ->label(_('message.miscellaneous_section'))
                                ->collapsible()
                                ->icon('heroicon-o-cog-6-tooth')
                                ->collapsed()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('site.theme')
                                        ->label(__('message.site_theme')),
                                    Select::make('site.timezone')
                                        ->label(__('message.site_timezone'))
                                        ->options(array_combine(
                                            DateTimeZone::listIdentifiers(),
                                            DateTimeZone::listIdentifiers()
                                        ))
                                        ->searchable()
                                        ->required(),
                                    Select::make('site.date_format')
                                        ->label(__('message.site_date_format'))
                                        ->options([
                                            'Y-m-d' => '2025-04-27 (Y-m-d)',
                                            'd-m-Y' => '27-04-2025 (d-m-Y)',
                                            'm/d/Y' => '04/27/2025 (m/d/Y)',
                                            'd/m/Y' => '27/04/2025 (d/m/Y)',
                                            'F j, Y' => 'April 27, 2025 (F j, Y)',
                                            'j F, Y' => '27 April, 2025 (j F, Y)',
                                            'M d, Y' => 'Apr 27, 2025 (M d, Y)',
                                            'D, M j, Y' => 'Sun, Apr 27, 2025 (D, M j, Y)',
                                            'l, F j, Y' => 'Sunday, April 27, 2025 (l, F j, Y)',
                                            'd.m.Y' => '27.04.2025 (d.m.Y)',
                                            'Y/m/d' => '2025/04/27 (Y/m/d)',
                                        ])
                                        ->searchable()
                                        ->required(),
                                    TextInput::make('site.currency')
                                        ->label(__('message.site_currency')),
                                    Select::make('site.language')
                                        ->label(__('message.site_language'))
                                        ->options([
                                            'ar' => 'Arabic',
                                            'de' => 'German',
                                            'en' => 'English',
                                            'es' => 'Spanish',
                                            'fr' => 'French',
                                            'ig' => 'Igbo',
                                            'it' => 'Italian',
                                            'ja' => 'Japanese',
                                            'nl' => 'Dutch',
                                            'zh' => 'Chinese (Simplified)',
                                        ])
                                        ->searchable()
                                        ->required(),
                                    TextInput::make('site.time_format')
                                        ->label(__('message.site_time_format')),
                                ]),
                        ])->icon('heroicon-o-clipboard-document-check'),
                    Tabs\Tab::make('Social Login')->label(__('message.social_login_tab'))
                        ->schema([
                            Section::make('Github')
                                ->icon('bi-github')
                                ->label(__('message.github_section'))
                                ->collapsible()
                                ->collapsed()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('social.github.client_id')
                                        ->label(__('message.client_id')),
                                    TextInput::make('social.github.client_secret')
                                        ->label('Client Secret')
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('social.github.redirect_uri')
                                        ->label(__('message.redirect_uri')),
                                ]),
                            Section::make('Twitter')
                                ->icon('bi-twitter-x')
                                ->collapsible()
                                ->collapsed()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('social.twitter.client_id')
                                        ->label(__('message.client_id')),
                                    TextInput::make('social.twitter.client_secret')
                                        ->label(__('message.client_secret'))
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('social.twitter.redirect_uri')
                                        ->label(__('message.redirect_uri')),
                                ]),
                            Section::make('Google')
                                ->icon('bi-google')
                                ->collapsible()
                                ->collapsed()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('social.google.client_id')
                                        ->label(__('message.client_id')),
                                    TextInput::make('social.google.client_secret')
                                        ->label('Client Secret')
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('social.google.redirect_uri')
                                        ->label(__('message.redirect_uri')),
                                ]),
                            Section::make('Yahoo')
                                ->icon('mdi-yahoo')
                                ->collapsible()
                                ->collapsed()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('social.yahoo.client_id')
                                        ->label(__('message.client_id')),
                                    TextInput::make('social.yahoo.client_secret')
                                        ->label('Client Secret')
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('social.yahoo.redirect_uri')
                                        ->label(__('message.redirect_uri')),
                                ]),
                            Section::make('Microsoft')
                                ->icon('bi-microsoft')
                                ->collapsible()
                                ->collapsed()
                                ->columns(3)
                                ->schema([
                                    TextInput::make('social.microsoft.client_id')
                                        ->label(__('message.client_id')),
                                    TextInput::make('social.microsoft.client_secret')
                                        ->label('Client Secret')
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('social.microsoft.redirect_uri')
                                        ->label(__('message.redirect_uri')),
                                ]),

                        ])->icon('heroicon-o-lock-closed'),
                    Tabs\Tab::make('Features')
                        ->label(__('message.features_tab'))
                        ->schema([
                            Section::make('Email Options')
                                ->icon('bi-mailbox-flag')
                                ->collapsible()
                                ->collapsed()
                                ->columns(1)
                                ->schema([
                                    Toggle::make('features.enable_email_verification')
                                        ->label(__('message.enable_email_verification')),
                                    Toggle::make('features.email_sending')
                                        ->label(__('message.email_sending')),
                                ]),
                            Section::make('SMS Options')
                                ->icon('bi-chat-text')
                                ->collapsible()
                                ->collapsed()
                                ->columns(1)
                                ->schema([
                                    Toggle::make('features.enable_phone_verification')
                                        ->label(__('message.enable_phone_verification')),
                                    Toggle::make('features.sms_sending')
                                        ->label(__('message.sms_sending')),
                                ]),

                            Section::make('Access Options')
                                ->icon('bi-key')
                                ->collapsible()
                                ->collapsed()
                                ->columns(1)
                                ->schema([
                                    Toggle::make('features.enable_two_factor_auth')
                                        ->label(__('message.enable_two_factor_auth')),
                                    Toggle::make('features.maintenance_mode')
                                        ->label(__('message.maintenance_mode')),
                                ]),
                            Section::make('Registration Options')
                                ->icon('mdi-lock-check-outline')
                                ->collapsible()
                                ->collapsed()
                                ->columns(1)
                                ->schema([
                                    Toggle::make('features.enable_registration')
                                        ->label(__('message.enable_registration')),
                                    Toggle::make('features.phone_email_at_registration')
                                        ->label(__('message.phone_email_at_registration')),
                                ]),

                        ])->icon('heroicon-o-battery-50'),
                    Tabs\Tab::make('Email Clients')
                        ->label(__('message.email_clients_tab'))
                        ->schema([
                            Select::make('email.client_name')
                                ->label(__('message.email_client_name'))
                                ->options([
                                    'resend' => 'Resend',
                                    'postmark' => 'Postmark',
                                    'mailgun' => 'Mailgun',
                                    'log' => 'Log [development only]',
                                ])
                                ->reactive()
                                ->afterStateHydrated(function (?string $state, callable $set) {
                                    if (blank($state)) {
                                        $set('email.client_name', 'resend');
                                    }
                                }),
                            TextInput::make('email.from.address')
                                ->label(__('message.from_address'))
                                ->default('no‑reply@yourdomain.com'),
                            Section::make('Mailgun')
                                ->icon('si-mailgun')
                                ->collapsible()
                                ->collapsed()
                                ->columns(2)
                                ->schema([
                                    TextInput::make('email.mailgun.api_key')
                                        ->label(__('message.mailgun_api_key'))
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('email.mailgun.endpoint')
                                        ->label(__('message.mailgun_endpoint')),
                                ])->visible(fn ($get) => $get('email.client_name') === 'mailgun'),
                            Section::make('Resend')
                                ->icon('si-resend')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextInput::make('email.resend.api_key')
                                        ->label(__('message.resend_api_key'))
                                        ->password()
                                        ->revealable(),
                                ])->visible(fn ($get) => $get('email.client_name') === 'resend'),
                            Section::make('Postmark')
                                ->icon('fas-p')
                                ->collapsible()
                                ->collapsed()
                                ->columns(2)
                                ->schema([
                                    TextInput::make('email.postmark.api_key')
                                        ->label(__('message.postmark_api_key'))
                                        ->password()
                                        ->revealable(),
                                ])->visible(fn ($get) => $get('email.client_name') === 'postmark'),

                        ])->icon('heroicon-o-envelope'),
                    Tabs\Tab::make('SMS Clients')
                        ->label(__('message.sms_clients_tab'))
                        ->schema([
                            Select::make('sms.client_name')
                                ->label(__('message.sms_client_name'))
                                ->options([
                                    'vonage' => 'Vonage',
                                    'africa_talking' => 'Africa talking',

                                ])
                                ->reactive()
                                ->afterStateHydrated(function (?string $state, callable $set) {
                                    if (blank($state)) {
                                        $set('sms.client_name', 'vonage');
                                    }
                                }),
                            TextInput::make('sms.from.address')
                                ->label(__('message.from_sms_address'))
                                ->default('SaaS PHP'),

                            Section::make('Vonage')
                                ->icon('si-vonage')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextInput::make('sms.vonage.api_key')
                                        ->label(__('message.vonage_api_key'))
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('sms.vonage.api_secret')
                                        ->label(__('message.vonage_secret_key'))
                                        ->password()
                                        ->revealable(),
                                ])->visible(fn ($get) => $get('sms.client_name') === 'vonage'),
                            Section::make('Africa talking')
                                ->icon('bi-globe-europe-africa')
                                ->collapsible()
                                ->collapsed()
                                ->columns(2)
                                ->schema([
                                    TextInput::make('sms.africa_talking.username')
                                        ->label(__('message.africa_talking_username')),
                                    TextInput::make('sms.africa_talking.api_key')
                                        ->label(__('message.africa_talking_api_key'))
                                        ->password()
                                        ->revealable(),
                                ])->visible(fn ($get) => $get('sms.client_name') === 'africa_talking'),

                        ])->icon('fas-sms'),
                    Tabs\Tab::make('Payment Gateways')
                        ->label(__('message.payment_gateways_tab'))
                        ->schema([
                            Section::make('Stripe')
                                ->icon('si-stripe')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextInput::make('payments.stripe.public_key')
                                        ->label(__('message.stripe_public_key')),
                                    TextInput::make('payments.stripe.secret_key')
                                        ->label(__('message.stripe_secret_key'))
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('payments.stripe.webhook_secret')
                                        ->label(__('message.stripe_webhook_secret'))
                                        ->password()
                                        ->revealable(),
                                ]),
                            Section::make('Paddle')
                                ->icon('si-paddle')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextInput::make('payments.paddle.vendor_id')
                                        ->label(__('message.paddle_vendor_id')),
                                    TextInput::make('payments.paddle.api_key')
                                        ->label(__('message.paddle_api_key'))
                                        ->password()
                                        ->revealable(),
                                    TextInput::make('payments.paddle.public_key')
                                        ->label(__('message.paddle_public_key')), // For webhook verification
                                ]),
                            Section::make('Paystack')
                                ->icon('fas-p')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextInput::make('payments.paystack.public_key')
                                        ->label(__('message.paystack_public_key')),
                                    TextInput::make('payments.paystack.secret_key')
                                        ->label(__('message.paystack_secret_key'))
                                        ->password()
                                        ->revealable(),
                                ]),
                        ])->icon('heroicon-o-banknotes'),
                ]),
            Actions::make([
                FormAction::make('save')
                    ->label(__('message.save_changes'))
                    ->icon('heroicon-m-check-circle')
                    ->requiresConfirmation()
                    ->action(function () {

                        $this->submit();
                    }),
            ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Save the form state to the settings table.
     */
    public function submit(): void
    {
        $this->validate();
        $state = $this->form->getState()['data'];
        $flat = Arr::dot($state);

        foreach ($flat as $key => $value) {
            if ($key === 'site.logo' && blank($value)) {
                continue;
            }

            // Handle file upload
            if ($value instanceof TemporaryUploadedFile) {
                $value = $value->storePublicly('logos', 'public');
            } elseif (is_array($value)) {
                $firstItem = reset($value);
                if ($firstItem instanceof TemporaryUploadedFile) {
                    $value = $firstItem->storePublicly('logos', 'public');
                } else {
                    $value = '';
                }
            }

            // Normalize to string (cast booleans to 'true'/'false')
            if (is_bool($value)) {
                $plain = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $plain = (string) $value;
            } else {
                $plain = '';
            }

            // Fire image-updated event on logo/favicon changes
            $isImageKey = in_array($key, ['site.logo']);
            $setting = Setting::firstWhere('key', $key);
            if ($setting && $isImageKey && $setting->value !== $plain) {
                event(new ImageUpdated($setting, [$setting->value]));
            }

            // Clear settings cache
            cache()->forget("setting.{$key}");

            // Save or update via encrypted cast
            Setting::updateOrCreate([
                'key' => $key,
            ], [
                'value' => $plain,
                'type' => is_bool($value) ? 'boolean' : 'string',
                'group' => explode('.', $key)[0],
            ]);
        }

        Notification::make()
            ->title(__('message.settings_saved'))
            ->success()
            ->send();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['site']['favicon']) && ! str_starts_with($data['site']['favicon'], 'storage/')) {
            $data['site']['favicon'] = 'storage/'.$data['site']['favicon'];
        }

        if (isset($data['site']['logo']) && ! str_starts_with($data['site']['logo'], 'storage/')) {
            $data['site']['logo'] = 'storage/'.$data['site']['logo'];
        }

        return $data;
    }

    /**
     * Livewire validation rules must mirror the $data property keys.
     */
    public function rules(): array
    {
        $rules = [
            'data.site.name' => ['required', 'max:255'],
            'data.site.url' => ['required', 'max:255', 'regex:/^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', 'not_regex:/http[s]?:\/\//', 'not_regex:/\//'],
            'data.site.description' => ['required', 'max:255'],
            'data.site.timezone' => ['required'],
            'data.site.date_format' => ['required'],
            'data.site.language' => ['required'],
            'data.site.logo' => ['nullable', 'image'],
        ];

        return $rules;
    }
}
