<?php

namespace App\Filament\Pages;

use App\Services\SettingsRepository;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class PermissionsMatrixPage extends Page
{
    protected string $view = 'filament.pages.permissions-matrix-page';

    protected static ?string $slug = 'matrix';

    protected static ?int $navigationSort = 3;

    /**
     * Canonical section list — the single source of truth from ADR-007.
     *
     * @var array<int, string>
     */
    public const SECTIONS = [
        'home',
        'news',
        'gallery',
        'contacts',
        'events',
        'players',
        'player_profiles',
    ];

    /**
     * Editable roles. Admin is always granted every section through
     * Gate::before (ADR-007), so it is rendered read-only.
     *
     * @var array<int, string>
     */
    public const ROLES = ['user', 'editor'];

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('admin') && $user->can('matrix.manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.matrix.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.navigation.system');
    }

    public function getTitle(): string
    {
        return __('filament.matrix.title');
    }

    public function mount(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $guestSections = $settings->get('guest_sections', []);
        $guestSections = is_array($guestSections) ? $guestSections : [];

        $values = [];

        foreach (self::ROLES as $roleName) {
            $role = Role::findByName($roleName, 'web');

            foreach (self::SECTIONS as $section) {
                $values['roles'][$roleName][$section] = $role->hasPermissionTo("section.{$section}.view");
            }
        }

        foreach (self::SECTIONS as $section) {
            $values['roles']['admin'][$section] = true;
            $values['guests'][$section] = (bool) ($guestSections[$section] ?? false);
        }

        $this->form->fill($values);
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function form(Schema $schema): Schema
    {
        $roleSections = [];

        foreach (self::ROLES as $roleName) {
            $roleSections[] = Section::make(__("filament.matrix.groups.{$roleName}"))
                ->schema($this->sectionToggles("roles.{$roleName}"))
                ->columns(2);
        }

        $roleSections[] = Section::make(__('filament.matrix.groups.admin'))
            ->description(__('filament.matrix.admin_locked'))
            ->schema($this->sectionToggles('roles.admin', locked: true))
            ->columns(2);

        $roleSections[] = Section::make(__('filament.matrix.groups.guests'))
            ->schema($this->sectionToggles('guests'))
            ->columns(2);

        return $schema
            ->statePath('data')
            ->components($roleSections);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        DB::transaction(function () use ($data, $settings): void {
            foreach (self::ROLES as $roleName) {
                /** @var Role $role */
                $role = Role::findByName($roleName, 'web');

                $this->syncRoleSections(
                    $role,
                    $this->selectedSections($data['roles'][$roleName] ?? []),
                );
            }

            // Admin is always granted every section (Gate::before, ADR-007).
            /** @var Role $adminRole */
            $adminRole = Role::findByName('admin', 'web');

            $this->syncRoleSections($adminRole, self::SECTIONS);

            $guestSections = [];

            foreach (self::SECTIONS as $section) {
                $guestSections[$section] = (bool) ($data['guests'][$section] ?? false);
            }

            $settings->setMany(['guest_sections' => $guestSections]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Notification::make()
            ->success()
            ->title(__('filament.matrix.saved'))
            ->send();

        $this->mount($settings);
    }

    /**
     * @return array<int, Toggle>
     */
    private function sectionToggles(string $statePrefix, bool $locked = false): array
    {
        $toggles = [];

        foreach (self::SECTIONS as $section) {
            $toggle = Toggle::make("{$statePrefix}.{$section}")
                ->label(__("filament.matrix.sections.{$section}"));

            if ($locked) {
                $toggle->default(true)->disabled()->dehydrated(false);
            }

            $toggles[] = $toggle;
        }

        return $toggles;
    }

    /**
     * Keep every non-section permission and replace the section ones.
     *
     * @param  array<int, string>  $sections
     */
    private function syncRoleSections(Role $role, array $sections): void
    {
        $preserved = $role->permissions()
            ->where('name', 'not like', 'section.%')
            ->pluck('name')
            ->all();

        $sectionPermissions = array_map(
            fn (string $section): string => "section.{$section}.view",
            $sections,
        );

        $role->syncPermissions(array_values(array_unique(array_merge($preserved, $sectionPermissions))));
    }

    /**
     * @param  array<string, mixed>  $roleState
     * @return array<int, string>
     */
    private function selectedSections(array $roleState): array
    {
        return array_values(array_filter(
            self::SECTIONS,
            fn (string $section): bool => (bool) ($roleState[$section] ?? false),
        ));
    }
}
