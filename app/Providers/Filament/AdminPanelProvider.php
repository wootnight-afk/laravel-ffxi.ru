<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\BackupPage;
use App\Filament\Pages\PermissionsMatrixPage;
use App\Filament\Pages\RoutesPage;
use App\Filament\Pages\SettingsPage;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Widgets\AdminDashboardStats;
use App\Filament\Widgets\RecentAuditLogs;
use App\Filament\Widgets\RegistrationsChart;
use App\Http\Middleware\EnsureAdminIpAllowed;
use App\Http\Middleware\EnsureAdminMultiFactorAuthentication;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\SetAdminPanelLocale;
use App\Services\TimezoneResolver;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ADR-011 §2.9: пользовательские timestamp-компоненты Filament
        // показываются в часовом поясе администратора. Closure вычисляется
        // при каждом обращении, то есть уже после аутентификации запроса;
        // строка, вычисленная здесь, зафиксировала бы null (panel()
        // выполняется в packageBooted(), до HTTP-запроса).
        FilamentTimezone::set(fn (): string => app(TimezoneResolver::class)->resolve(auth()->user()));

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(EditProfile::class)
            ->multiFactorAuthentication(
                providers: [
                    AppAuthentication::make()
                        ->recoverable(),
                ],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureAdminMultiFactorAuthentication::class)
            ->brandName('FFXI Phoenix')
            ->favicon(asset('favicon.ico'))
            ->darkMode(true)
            ->colors([
                // Teal-500 (#14b8a6) — единый бренд с публичным сайтом
                // (adminreview.md §10.3).
                'primary' => Color::Teal,
            ])
            ->navigationGroups([
                // Stable keys keep navigation items independent from the locale
                // at panel boot; labels are resolved lazily at render time.
                'main' => NavigationGroup::make(fn (): string => __('filament.navigation.main')),
                'community' => NavigationGroup::make(fn (): string => __('filament.navigation.community')),
                'content' => NavigationGroup::make(fn (): string => __('filament.navigation.content')),
                'system' => NavigationGroup::make(fn (): string => __('filament.navigation.system')),
            ])
            ->navigationItems([
                NavigationItem::make(fn (): string => __('filament.home.label'))
                    ->key('content-home')->group('content')->sort(0)
                    ->icon('heroicon-o-home')
                    ->url(fn (): string => EditPage::getUrl(['record' => 'sys.home']))
                    ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false)),
                NavigationItem::make(fn (): string => __('filament.navigation.menus'))
                    ->key('content-menus')->group('content')->sort(1)
                    ->icon('heroicon-o-bars-3')
                    ->visible(fn (): bool => auth()->user()?->can('pages.manage') ?? false),
                NavigationItem::make(fn (): string => __('filament.navigation.components'))
                    ->key('content-components')->group('content')->sort(2)
                    ->icon('heroicon-o-squares-2x2')
                    ->visible(fn (): bool => NewsResource::canViewAny()),
            ])
            // Language switch right before the user menu (ADR-011 §2.24).
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.components.language-switcher'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                AdminDashboard::class,
                PermissionsMatrixPage::class,
                RoutesPage::class,
                SettingsPage::class,
                BackupPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                AdminDashboardStats::class,
                RegistrationsChart::class,
                RecentAuditLogs::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetAdminPanelLocale::class,
                EnsureAdminIpAllowed::class,
                RequireMfa::class,
            ])
            // Livewire re-applies persistent middleware to a "fake" request built
            // via Request::duplicate(). That request's cookies are already
            // decrypted, so re-running the cookie/session stack (EncryptCookies,
            // AddQueuedCookiesToResponse, StartSession) double-decrypts them and
            // nulls every encrypted cookie. AuthenticateSession then sees a null
            // remember_web_* while viaRemember() is true and logs the user out on
            // the next Livewire update. The Livewire update route already runs the
            // full web stack, so only panel-specific middleware that is absent
            // from it needs to be persistent here. Filament already registers
            // Authenticate/AuthenticateSession persistently itself
            // (FilamentServiceProvider::boot), and RequireMfa is registered
            // app-wide (AppServiceProvider::boot), so neither is repeated here.
            ->persistentMiddleware([
                SetAdminPanelLocale::class,
                EnsureAdminIpAllowed::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Pinned record-action column in the pages registry, which is
            // wider than the panel content column (see the stylesheet header).
            ->assets([
                Css::make('admin-tables')->relativePublicPath('css/admin-tables.css'),
            ]);
    }
}
