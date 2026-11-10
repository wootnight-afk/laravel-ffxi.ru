<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Declarative profile of every page handled by the unified page editor.
 *
 * A profile declares the editable sections, the settings provider that owns the
 * data of a system page and the concrete field-to-setting mapping. The unified
 * editor builds a single form from the union of all profiles and disables the
 * parts that do not apply to the page type being edited, so the UI itself
 * explains the limits of each type.
 *
 * Profile keys: `cms` for the `pages` table and the route name for a system
 * page (e.g. `news.index`).
 */
final class PageEditorProfile
{
    public const KIND_CMS = 'cms';

    public const KIND_SYSTEM = 'system';

    public const SECTION_CONTENT = 'content';

    public const SECTION_SLUG = 'slug';

    public const SECTION_MENU = 'menu';

    public const SECTION_SEO = 'seo';

    public const SECTION_IMAGES = 'images';

    public const SECTION_COMPONENTS = 'components';

    /**
     * Profiles keyed by locale, so a language switch inside the admin panel
     * rebuilds the translated titles instead of serving stale strings.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private static array $profiles = [];

    /**
     * All profiles keyed by profile key.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function profiles(): array
    {
        $locale = app()->getLocale();

        return self::$profiles[$locale] ??= self::buildProfiles();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function buildProfiles(): array
    {
        return [
            'cms' => self::cmsProfile(),
            'home' => self::systemProfile(
                key: 'home',
                title: __('filament.page_registry.labels.home'),
                url: '/',
                provider: HomePageContent::class,
                auditAction: 'home.updated',
                sections: [
                    self::SECTION_SLUG => self::locked(__('filament.page_editor.fixed_route', ['url' => '/'])),
                    self::SECTION_MENU => self::editable(),
                    self::SECTION_SEO => self::editable(),
                    self::SECTION_IMAGES => self::editable(),
                    self::SECTION_COMPONENTS => self::editable(),
                ],
                imageSlots: [
                    self::imageSlot('hero_logo', HomeImageUploader::SLOT_HERO, __('filament.page_editor.fields.hero_logo'), 'KEY_HERO_LOGO', withDimensions: false),
                    self::imageSlot('main_image', HomeImageUploader::SLOT_IMAGE, __('filament.page_editor.fields.main_image'), 'KEY_MAIN_IMAGE'),
                ],
                componentKeys: [
                    self::component('is_main_image_component_enabled', 'KEY_IS_MAIN_IMAGE_COMPONENT_ENABLED', __('filament.home.fields.is_main_image_component_enabled')),
                    self::component('is_body_component_enabled', 'KEY_IS_BODY_COMPONENT_ENABLED', __('filament.home.fields.is_body_component_enabled')),
                ],
                menuRoute: 'home',
                menuLabelKey: 'site.nav.home',
            ),
            'news.index' => self::systemProfile(
                key: 'news.index',
                title: __('filament.page_registry.labels.news'),
                url: '/news',
                provider: NewsPageContent::class,
                auditAction: 'news_page.updated',
                sections: [
                    self::SECTION_SLUG => self::locked(__('filament.page_editor.fixed_route', ['url' => '/news'])),
                    self::SECTION_MENU => self::editable(),
                    self::SECTION_SEO => self::editable(),
                    self::SECTION_IMAGES => self::editable(),
                    self::SECTION_COMPONENTS => self::editable(),
                ],
                imageSlots: [
                    self::imageSlot('main_image', HomeImageUploader::SLOT_NEWS_PAGE, __('filament.news_page.fields.main_image'), 'KEY_MAIN_IMAGE'),
                ],
                componentKeys: [
                    self::component('is_news_component_enabled', 'KEY_IS_NEWS_COMPONENT_ENABLED', __('filament.news_page.fields.is_news_component_enabled'), adminOnly: true),
                ],
                menuRoute: 'news.index',
                menuLabelKey: 'site.nav.news',
            ),
            'gallery.index' => self::systemProfile(
                key: 'gallery.index',
                title: __('filament.page_registry.labels.gallery'),
                url: '/gallery',
                provider: GalleryPageContent::class,
                auditAction: 'gallery_page.updated',
                sections: [
                    self::SECTION_SLUG => self::locked(__('filament.page_editor.fixed_route', ['url' => '/gallery'])),
                    self::SECTION_MENU => self::editable(),
                    self::SECTION_SEO => self::editable(),
                    self::SECTION_IMAGES => self::unavailable(),
                    self::SECTION_COMPONENTS => self::editable(),
                ],
                componentKeys: [
                    self::component('is_gallery_component_enabled', 'KEY_IS_GALLERY_COMPONENT_ENABLED', __('filament.gallery_page.fields.is_gallery_component_enabled')),
                ],
                menuRoute: 'gallery.index',
                menuLabelKey: 'site.nav.gallery',
            ),
            'contacts' => self::systemProfile(
                key: 'contacts',
                title: __('filament.page_registry.labels.contacts'),
                url: '/contacts',
                provider: ContactsPageContent::class,
                auditAction: 'contacts.updated',
                sections: [
                    self::SECTION_SLUG => self::locked(__('filament.page_editor.fixed_route', ['url' => '/contacts'])),
                    self::SECTION_MENU => self::editable(),
                    self::SECTION_SEO => self::editable(),
                    self::SECTION_IMAGES => self::unavailable(),
                    self::SECTION_COMPONENTS => self::unavailable(),
                ],
                menuRoute: 'contacts',
                menuLabelKey: 'site.nav.contacts',
            ),
            'events.index' => self::systemProfile(
                key: 'events.index',
                title: __('filament.page_registry.labels.events'),
                url: '/events',
                provider: EventsPageContent::class,
                auditAction: 'events.updated',
                sections: [
                    self::SECTION_SLUG => self::locked(__('filament.page_editor.fixed_route', ['url' => '/events'])),
                    self::SECTION_MENU => self::editable(),
                    self::SECTION_SEO => self::editable(),
                    self::SECTION_IMAGES => self::unavailable(),
                    self::SECTION_COMPONENTS => self::unavailable(),
                ],
                menuRoute: 'events.index',
                menuLabelKey: 'site.nav.events',
            ),
            'events.create' => self::systemProfile(
                key: 'events.create',
                title: __('filament.page_registry.labels.event_create'),
                url: '/events/create',
                provider: EventsCreatePageContent::class,
                auditAction: 'events_create.updated',
                sections: [
                    self::SECTION_SLUG => self::locked(__('filament.page_editor.fixed_route', ['url' => '/events/create'])),
                    self::SECTION_MENU => self::locked(__('filament.page_editor.menu_not_applicable')),
                    self::SECTION_SEO => self::editable(),
                    self::SECTION_IMAGES => self::unavailable(),
                    self::SECTION_COMPONENTS => self::unavailable(),
                ],
                menuRoute: null,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function cmsProfile(): array
    {
        return [
            'key' => 'cms',
            'kind' => self::KIND_CMS,
            'title' => __('filament.page_registry.kinds.cms'),
            'url' => '/{slug}',
            'sections' => [
                self::SECTION_CONTENT => self::editable(),
                self::SECTION_SLUG => self::editable(),
                self::SECTION_MENU => self::editable(),
                self::SECTION_SEO => self::editable(),
                self::SECTION_IMAGES => self::unavailable(),
                self::SECTION_COMPONENTS => self::unavailable(),
            ],
            'provider' => null,
            'auditAction' => null,
            'imageSlots' => [],
            'componentKeys' => [],
            'menuRoute' => null,
            'menuLabelKey' => null,
        ];
    }

    /**
     * @param  array<string, array{editable: bool, reason: string}>  $sections
     * @param  array<int, array<string, mixed>>  $imageSlots
     * @param  array<int, array<string, mixed>>  $componentKeys
     * @return array<string, mixed>
     */
    private static function systemProfile(
        string $key,
        string $title,
        string $url,
        string $provider,
        string $auditAction,
        array $sections,
        array $imageSlots = [],
        array $componentKeys = [],
        ?string $menuRoute = null,
        ?string $menuLabelKey = null,
    ): array {
        return [
            'key' => $key,
            'kind' => self::KIND_SYSTEM,
            'title' => $title,
            'url' => $url,
            'sections' => [self::SECTION_CONTENT => self::editable()] + $sections,
            'provider' => $provider,
            'auditAction' => $auditAction,
            'imageSlots' => $imageSlots,
            'componentKeys' => $componentKeys,
            'menuRoute' => $menuRoute,
            'menuLabelKey' => $menuLabelKey,
        ];
    }

    /** @return array{editable: bool, reason: string} */
    private static function editable(): array
    {
        return ['editable' => true, 'reason' => ''];
    }

    /** @return array{editable: bool, reason: string} */
    private static function locked(string $reason): array
    {
        return ['editable' => false, 'reason' => $reason];
    }

    /** @return array{editable: bool, reason: string} */
    private static function unavailable(): array
    {
        return ['editable' => false, 'reason' => __('filament.page_editor.not_applicable')];
    }

    /** @return array<string, mixed> */
    private static function imageSlot(
        string $field,
        string $slot,
        string $label,
        string $pathKey,
        bool $withDimensions = true,
    ): array {
        return [
            'field' => $field,
            'slot' => $slot,
            'label' => $label,
            'pathKey' => $pathKey,
            'widthKey' => $withDimensions ? 'KEY_MAIN_IMAGE_WIDTH' : null,
            'heightKey' => $withDimensions ? 'KEY_MAIN_IMAGE_HEIGHT' : null,
            'resetField' => 'reset_'.$field,
        ];
    }

    /** @return array<string, mixed> */
    private static function component(string $field, string $key, string $label, bool $adminOnly = false): array
    {
        return [
            'field' => $field,
            'key' => $key,
            'label' => $label,
            'adminOnly' => $adminOnly,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forKey(string $key): ?array
    {
        return self::profiles()[$key] ?? null;
    }

    /**
     * Profile keys that can be created through the editor.
     *
     * Only CMS pages can be created: a system page is a fixed route that
     * already exists and has no record of its own.
     *
     * @return array<int, string>
     */
    public static function creatableKeys(): array
    {
        return ['cms'];
    }

    public static function isCreatable(string $key): bool
    {
        return in_array($key, self::creatableKeys(), true);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function sectionEditable(array $profile, string $section): bool
    {
        return (bool) ($profile['sections'][$section]['editable'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function sectionReason(array $profile, string $section): string
    {
        return (string) ($profile['sections'][$section]['reason'] ?? '');
    }
}
