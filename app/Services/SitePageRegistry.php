<?php

namespace App\Services;

use App\Http\Controllers\CabinetController;
use App\Models\Page;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class SitePageRegistry
{
    /** @var array<string, array{string, string}> */
    private const PAGES = [
        'home' => ['home', 'system'],
        'news.index' => ['news', 'system'],
        'gallery.index' => ['gallery', 'system'],
        'contacts' => ['contacts', 'system'],
        'events.index' => ['events', 'system'],
        'events.create' => ['event_create', 'system'],
        'events.edit' => ['event_edit', 'template'],
        'events.show' => ['event', 'template'],
        'news.show' => ['article', 'template'],
        'gallery.album' => ['album', 'template'],
        'gallery.photo' => ['photo', 'template'],
        'players.dashboard' => ['players', 'system'],
        'players.directory' => ['directory', 'system'],
        'players.show' => ['player', 'template'],
        'activity.index' => ['activity', 'system'],
        'cabinet.show' => ['cabinet', 'system'],
        'cabinet.tab' => ['cabinet', 'cabinet'],
        'register' => ['register', 'auth'],
        'login' => ['login', 'auth'],
        'password.request' => ['password_request', 'auth'],
        'password.reset' => ['password_reset', 'template'],
        'verification.notice' => ['verification', 'auth'],
        'mfa.challenge' => ['mfa', 'auth'],
        'cookie' => ['cookie', 'system'],
        'privacy' => ['privacy', 'system'],
        'page.show' => ['cms_template', 'template'],
    ];

    /** @return array<int, array<string, mixed>|Page> */
    public function records(): array
    {
        $records = Page::query()->orderBy('menu_order')->orderBy('id')->get()->all();
        foreach (self::PAGES as $name => [$label, $kind]) {
            $route = Route::getRoutes()->getByName($name);
            if ($route === null || ! in_array('GET', $route->methods(), true)) {
                // A missing route must never take the whole registry down:
                // the row is skipped and the mismatch is reported in the log
                // and on the route reference page ("missing" category).
                Log::warning('Classified page route is not registered.', ['route' => $name]);

                continue;
            }
            if ($name === 'cabinet.tab') {
                foreach (CabinetController::TABS as $tab) {
                    $records[] = $this->record($name.'.'.$tab, 'cabinet_'.$tab, 'cabinet', '/cabinet/'.$tab);
                }

                continue;
            }
            $records[] = $this->record($name, $label, $kind, '/'.ltrim($route->uri(), '/'));
        }

        return $records;
    }

    /** @return array<string, mixed> */
    private function record(string $name, string $label, string $kind, string $url): array
    {
        return [
            '__key' => 'system:'.$name,
            'title' => __('filament.page_registry.labels.'.$label),
            'slug' => $url,
            'url' => $url,
            'kind' => $kind,
            'route' => $name,
            'is_published' => null,
            'show_in_menu' => null,
            'show_in_footer' => null,
            'menu_order' => null,
        ];
    }

    /**
     * Classify every registered route for the admin route reference page.
     *
     * Categories make the infrastructure explicit: an administrative page is
     * marked "filament", an endpoint is "api" and everything that only exists
     * to serve the application is "technical". Only "public" and "auth" rows
     * are user-facing pages of the site.
     *
     * @return array<int, array{name: ?string, uri: string, methods: array<int, string>, included: bool, category: string, reason: string}>
     */
    public function classification(): array
    {
        $result = [];
        $registered = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name !== null) {
                $registered[$name] = true;
            }

            $get = in_array('GET', $route->methods(), true);
            $included = $get && isset(self::PAGES[$name ?? '']);
            [$category, $reason] = $this->classify($route, $included, $name ?? '');
            $result[] = [
                'name' => $name,
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'included' => $included,
                'category' => $category,
                'reason' => $reason,
            ];
        }

        // A classified page whose route disappeared is reported explicitly
        // instead of silently vanishing from the reference page.
        foreach (self::PAGES as $name => [$label, $kind]) {
            if (isset($registered[$name])) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'uri' => '—',
                'methods' => [],
                'included' => false,
                'category' => 'missing',
                'reason' => 'classified page route is not registered',
            ];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function classify(RoutingRoute $route, bool $included, string $name): array
    {
        if ($included) {
            // Sign-in, registration, password and MFA pages are user-facing,
            // but they are not part of the public content of the site.
            if ((self::PAGES[$name][1] ?? 'system') === 'auth') {
                return ['auth', 'sign-in or account verification page, guest or authenticated only'];
            }

            return ['public', 'user-facing page or dynamic page template'];
        }

        if (! in_array('GET', $route->methods(), true)) {
            return ['write', 'write or technical action, not a GET page'];
        }

        $uri = $route->uri();

        return match (true) {
            $name === 'verification.verify' => ['technical', 'signed email verification action, mutates account state'],
            str_starts_with($name, 'filament.admin.') => ['filament', 'administrative panel, not a user site page'],
            str_starts_with($name, 'filament.exports.'), str_starts_with($name, 'filament.imports.') => ['technical', 'technical file download'],
            str_starts_with($uri, 'livewire-') => ['technical', 'Livewire asset, temporary file or component endpoint'],
            $uri === 'up' => ['technical', 'healthcheck'],
            $name === 'storage.local' => ['technical', 'file delivery, not a page'],
            str_starts_with($uri, 'api/') => ['api', 'API endpoint'],
            default => ['unclassified', 'unclassified GET route: review required'],
        };
    }
}
