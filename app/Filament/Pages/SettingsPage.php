<?php

namespace App\Filament\Pages;

use App\Rules\IpAllowlist;
use App\Services\SettingsRepository;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnexpectedValueException;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class SettingsPage extends Page
{
    protected string $view = 'filament.pages.settings-page';

    protected static ?string $slug = 'settings';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 9;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('admin') && $user->can('settings.manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.settings.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public function getTitle(): string
    {
        return __('filament.settings.title');
    }

    public function mount(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $values = array_replace($this->defaults(), array_intersect_key(
            $settings->all(),
            $this->defaults(),
        ));
        $defaultGuestSections = $this->defaults()['guest_sections'];
        $storedGuestSections = $values['guest_sections'];

        if (! is_array($storedGuestSections)) {
            throw new UnexpectedValueException('The guest_sections setting must be an object of known section flags.');
        }

        $values['guest_sections'] = array_replace(
            $defaultGuestSections,
            array_intersect_key($storedGuestSections, $defaultGuestSections),
        );
        $values['admin_ip_allowlist_text'] = implode(
            "\n",
            $this->readAllowlist($settings->get('admin_ip_allowlist', [])),
        );
        unset($values['admin_ip_allowlist']);

        $this->form->fill($values);
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('filament.settings.groups.general'))
                    ->schema([
                        TextInput::make('pd_policy_version')
                            ->label(__('filament.settings.fields.pd_policy_version'))
                            ->required()
                            ->maxLength(20),
                        TextInput::make('timezone_display')
                            ->label(__('filament.settings.fields.timezone_display'))
                            ->required()
                            ->rule('timezone')
                            ->maxLength(64),
                    ])
                    ->columns(2),
                Section::make(__('filament.settings.groups.registration'))
                    ->schema([
                        Toggle::make('registration_open')
                            ->label(__('filament.settings.fields.registration_open')),
                        Select::make('player_news_moderation')
                            ->label(__('filament.settings.fields.player_news_moderation'))
                            ->options([
                                'post' => __('filament.settings.options.moderation_post'),
                                'pre' => __('filament.settings.options.moderation_pre'),
                            ])
                            ->required(),
                        Toggle::make('guest_sections.home')
                            ->label(__('filament.settings.fields.guest_sections_home')),
                        Toggle::make('guest_sections.news')
                            ->label(__('filament.settings.fields.guest_sections_news')),
                        Toggle::make('guest_sections.gallery')
                            ->label(__('filament.settings.fields.guest_sections_gallery')),
                        Toggle::make('guest_sections.contacts')
                            ->label(__('filament.settings.fields.guest_sections_contacts')),
                        Toggle::make('guest_sections.events')
                            ->label(__('filament.settings.fields.guest_sections_events')),
                        Toggle::make('guest_sections.players')
                            ->label(__('filament.settings.fields.guest_sections_players')),
                        Toggle::make('guest_sections.player_profiles')
                            ->label(__('filament.settings.fields.guest_sections_player_profiles')),
                    ])
                    ->columns(2),
                Section::make(__('filament.settings.groups.comments'))
                    ->schema([
                        Select::make('comments_moderation')
                            ->label(__('filament.settings.fields.comments_moderation'))
                            ->options([
                                'none' => __('filament.settings.options.comments_none'),
                                'reputation' => __('filament.settings.options.comments_reputation'),
                                'all' => __('filament.settings.options.comments_all'),
                            ])
                            ->required(),
                    ]),
                Section::make(__('filament.settings.groups.chat'))
                    ->schema([
                        Toggle::make('chat_enabled')
                            ->label(__('filament.settings.fields.chat_enabled')),
                        TextInput::make('chat_polling_interval')
                            ->label(__('filament.settings.fields.chat_polling_interval'))
                            ->integer()
                            ->minValue(3)
                            ->maxValue(30)
                            ->required(),
                        TextInput::make('chat_max_length')
                            ->label(__('filament.settings.fields.chat_max_length'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(2000)
                            ->required(),
                        Toggle::make('guest_chat_enabled')
                            ->label(__('filament.settings.fields.guest_chat_enabled')),
                    ])
                    ->columns(2),
                Section::make(__('filament.settings.groups.events'))
                    ->schema([
                        Toggle::make('events_enabled')
                            ->label(__('filament.settings.fields.events_enabled')),
                    ]),
                Section::make(__('filament.settings.groups.gallery_profile'))
                    ->schema([
                        TextInput::make('gallery_max_albums_per_user')
                            ->label(__('filament.settings.fields.gallery_max_albums_per_user'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('gallery_max_photo_mb')
                            ->label(__('filament.settings.fields.gallery_max_photo_mb'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(32)
                            ->required(),
                        TextInput::make('gallery_max_photos_per_day')
                            ->label(__('filament.settings.fields.gallery_max_photos_per_day'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                        TextInput::make('social_max_links_per_user')
                            ->label(__('filament.settings.fields.social_max_links_per_user'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(50)
                            ->required(),
                        Toggle::make('ranks_enabled')
                            ->label(__('filament.settings.fields.ranks_enabled')),
                        Toggle::make('default_profile_public')
                            ->label(__('filament.settings.fields.default_profile_public')),
                    ])
                    ->columns(2),
                Section::make(__('filament.settings.groups.security'))
                    ->schema([
                        Toggle::make('admin_2fa_required')
                            ->label(__('filament.settings.fields.admin_2fa_required')),
                        Textarea::make('admin_ip_allowlist_text')
                            ->label(__('filament.settings.fields.admin_ip_allowlist'))
                            ->helperText(__('filament.settings.fields.admin_ip_allowlist_help'))
                            ->rows(4)
                            ->rule(new IpAllowlist),
                        TextInput::make('reauth_password')
                            ->label(__('filament.settings.fields.reauth_password'))
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get): bool => $this->allowlistWillChange(
                                (string) $get('admin_ip_allowlist_text'),
                            ))
                            ->visible(fn (Get $get): bool => $this->allowlistWillChange(
                                (string) $get('admin_ip_allowlist_text'),
                            ))
                            ->dehydrated(),
                        Toggle::make('admin_new_ip_notify')
                            ->label(__('filament.settings.fields.admin_new_ip_notify')),
                    ])
                    ->description(__('filament.settings.fields.admin_ip_allowlist_warning')),
                Section::make(__('filament.settings.groups.activity'))
                    ->schema([
                        Toggle::make('bell_enabled')
                            ->label(__('filament.settings.fields.bell_enabled')),
                        Toggle::make('activity_enabled')
                            ->label(__('filament.settings.fields.activity_enabled')),
                        Toggle::make('notifications_enabled')
                            ->label(__('filament.settings.fields.notifications_enabled')),
                        TextInput::make('activity_retention_days')
                            ->label(__('filament.settings.fields.activity_retention_days'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(3650)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $allowlist = $this->parseAllowlist((string) ($data['admin_ip_allowlist_text'] ?? ''));
        $currentAllowlist = $this->readAllowlist($settings->get('admin_ip_allowlist', []));

        if ($allowlist !== $currentAllowlist) {
            $this->validate([
                'data.reauth_password' => ['required', 'current_password'],
            ]);
        }

        $values = [
            'pd_policy_version' => $data['pd_policy_version'],
            'timezone_display' => $data['timezone_display'],
            'registration_open' => (bool) $data['registration_open'],
            'player_news_moderation' => $data['player_news_moderation'],
            'comments_moderation' => $data['comments_moderation'],
            'chat_enabled' => (bool) $data['chat_enabled'],
            'chat_polling_interval' => (int) $data['chat_polling_interval'],
            'chat_max_length' => (int) $data['chat_max_length'],
            'guest_chat_enabled' => (bool) $data['guest_chat_enabled'],
            'events_enabled' => (bool) $data['events_enabled'],
            'gallery_max_albums_per_user' => (int) $data['gallery_max_albums_per_user'],
            'gallery_max_photo_mb' => (int) $data['gallery_max_photo_mb'],
            'gallery_max_photos_per_day' => (int) $data['gallery_max_photos_per_day'],
            'social_max_links_per_user' => (int) $data['social_max_links_per_user'],
            'ranks_enabled' => (bool) $data['ranks_enabled'],
            'default_profile_public' => (bool) $data['default_profile_public'],
            'guest_sections' => $data['guest_sections'],
            'admin_2fa_required' => (bool) $data['admin_2fa_required'],
            'admin_ip_allowlist' => $allowlist,
            'admin_new_ip_notify' => (bool) $data['admin_new_ip_notify'],
            'bell_enabled' => (bool) $data['bell_enabled'],
            'activity_enabled' => (bool) $data['activity_enabled'],
            'notifications_enabled' => (bool) $data['notifications_enabled'],
            'activity_retention_days' => (int) $data['activity_retention_days'],
        ];

        $settings->setMany($values);

        Notification::make()
            ->success()
            ->title(__('filament.settings.saved'))
            ->send();

        $this->mount($settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'pd_policy_version' => 'draft',
            'timezone_display' => 'Europe/Moscow',
            'registration_open' => false,
            'player_news_moderation' => 'post',
            'comments_moderation' => 'reputation',
            'chat_enabled' => true,
            'chat_polling_interval' => 5,
            'chat_max_length' => 500,
            'guest_chat_enabled' => false,
            'events_enabled' => true,
            'gallery_max_albums_per_user' => 10,
            'gallery_max_photo_mb' => 8,
            'gallery_max_photos_per_day' => 50,
            'social_max_links_per_user' => 10,
            'ranks_enabled' => true,
            'default_profile_public' => false,
            'guest_sections' => [
                'home' => true,
                'news' => true,
                'gallery' => true,
                'contacts' => true,
                'events' => true,
                'players' => false,
                'player_profiles' => false,
            ],
            'admin_2fa_required' => false,
            'admin_ip_allowlist' => [],
            'admin_new_ip_notify' => true,
            'bell_enabled' => true,
            'activity_enabled' => true,
            'notifications_enabled' => true,
            'activity_retention_days' => 180,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function readAllowlist(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new UnexpectedValueException('The admin IP allowlist setting must be a JSON array.');
        }

        return $this->normalizeAllowlist($value);
    }

    /**
     * @return array<int, string>
     */
    private function parseAllowlist(string $value): array
    {
        return $this->normalizeAllowlist(preg_split('/\R/', $value) ?: []);
    }

    /**
     * @return array<int, string>
     */
    private function candidateAllowlist(string $value): array
    {
        $entries = array_map(
            trim(...),
            preg_split('/\R/', $value) ?: [],
        );
        $entries = array_values(array_filter($entries, fn (string $entry): bool => $entry !== ''));
        $entries = array_values(array_unique($entries));
        sort($entries);

        return $entries;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, string>
     */
    private function normalizeAllowlist(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                throw new UnexpectedValueException('Each admin IP allowlist entry must be a string.');
            }

            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (! IpAllowlist::isValidEntry($entry)) {
                throw new UnexpectedValueException('The admin IP allowlist contains an invalid IP or CIDR.');
            }

            $normalized[] = $entry;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    private function allowlistWillChange(string $newAllowlist): bool
    {
        $current = $this->readAllowlist(
            app(SettingsRepository::class)->get('admin_ip_allowlist', []),
        );

        return $this->candidateAllowlist($newAllowlist) !== $current;
    }
}
