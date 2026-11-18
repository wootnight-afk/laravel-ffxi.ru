<?php

use App\Models\Page;
use App\Services\PageMenuManager;
use App\Services\SettingsRepository;
use App\Services\SiteNavigation;

/**
 * Public footer labels come from the Header/Footer editor (`label_ru` /
 * `label_en` of the navigation settings), never from the CMS page title
 * (STAGE-1 §20).
 */
beforeEach(function () {
    Page::query()->forceDelete();

    $this->rules = Page::query()->create([
        'title' => 'Правила сообщества',
        // Deliberately different from the menu label: the footer must not use it.
        'title_en' => 'Page title that the footer must ignore',
        'slug' => 'pravila-soobschestva',
        'body' => 'Русские правила сообщества.',
        'body_en' => 'English community rules.',
        'is_published' => true,
        'show_in_footer' => true,
    ]);

    $this->about = Page::query()->create([
        'title' => 'О сообществе',
        'title_en' => 'Another page title the footer must ignore',
        'slug' => 'o-soobschestve',
        'body' => 'Русский текст о сообществе.',
        'body_en' => 'English text about the community.',
        'is_published' => true,
        'show_in_footer' => true,
    ]);

    $this->footerItems = [
        ['id' => 'contacts', 'type' => 'route', 'target' => 'contacts', 'label_ru' => 'Контакты', 'label_en' => 'Contacts', 'order' => 0, 'image' => null],
        ['id' => 'login', 'type' => 'route', 'target' => 'login', 'label_ru' => 'Авторизация', 'label_en' => 'Log in', 'order' => 1, 'image' => null],
        ['id' => 'rules', 'type' => 'page', 'target' => (string) $this->rules->getKey(), 'label_ru' => 'Правила сообщества', 'label_en' => 'Community Rules', 'order' => 2, 'image' => 'footer/rules.svg'],
        ['id' => 'about', 'type' => 'page', 'target' => (string) $this->about->getKey(), 'label_ru' => 'О сообществе', 'label_en' => 'About the Community', 'order' => 3, 'image' => null],
    ];

    app(SettingsRepository::class)->set(SiteNavigation::KEY_FOOTER_ITEMS, $this->footerItems);
});

function storedFooterHtml($test, string $locale): string
{
    $html = $test->withSession(['locale' => $locale])->get('/')->assertOk()->getContent();
    $start = strpos($html, '<footer');

    return $start === false ? '' : substr($html, $start);
}

it('takes the russian footer labels from the footer editor', function () {
    $footer = storedFooterHtml($this, 'ru');

    expect($footer)->toContain('Контакты')
        ->toContain('Авторизация')
        ->toContain('Правила сообщества')
        ->toContain('О сообществе')
        ->not->toContain('Page title that the footer must ignore');
});

it('takes the english footer labels from the footer editor', function () {
    $footer = storedFooterHtml($this, 'en');

    expect($footer)->toContain('Contacts')
        ->toContain('Log in')
        ->toContain('Community Rules')
        ->toContain('About the Community')
        ->not->toContain('Правила сообщества')
        ->not->toContain('О сообществе')
        ->not->toContain('Page title that the footer must ignore');
});

it('reflects an english label edited in the footer editor', function () {
    $items = $this->footerItems;
    $items[2]['label_en'] = 'Server Rules';
    app(SettingsRepository::class)->set(SiteNavigation::KEY_FOOTER_ITEMS, $items);

    $footer = storedFooterHtml($this, 'en');

    expect($footer)->toContain('Server Rules')
        ->not->toContain('Community Rules');
});

it('keeps the english label when the russian one changes', function () {
    $items = $this->footerItems;
    $items[2]['label_ru'] = 'Правила сервера';
    app(SettingsRepository::class)->set(SiteNavigation::KEY_FOOTER_ITEMS, $items);

    expect(storedFooterHtml($this, 'ru'))->toContain('Правила сервера')
        ->and(storedFooterHtml($this, 'en'))->toContain('Community Rules')
        ->not->toContain('Правила сервера');
});

it('falls back to the russian label when the english one is empty', function () {
    $items = $this->footerItems;
    $items[2]['label_en'] = '';
    app(SettingsRepository::class)->set(SiteNavigation::KEY_FOOTER_ITEMS, $items);

    expect(storedFooterHtml($this, 'en'))->toContain('Правила сообщества')
        ->not->toContain('Page title that the footer must ignore');
});

it('keeps urls, order, images and composition across languages', function () {
    $rulesUrl = route('page.show', ['page' => 'pravila-soobschestva']);
    $aboutUrl = route('page.show', ['page' => 'o-soobschestve']);

    foreach (['ru', 'en'] as $locale) {
        expect(storedFooterHtml($this, $locale))
            ->toContain('href="'.$rulesUrl.'"')
            ->toContain('href="'.$aboutUrl.'"')
            ->toContain(asset('storage/footer/rules.svg'));
    }

    $positions = array_map(
        fn (string $label): int|false => strpos(storedFooterHtml($this, 'ru'), $label),
        ['Контакты', 'Авторизация', 'Правила сообщества', 'О сообществе'],
    );

    expect($positions[0])->toBeLessThan($positions[1])
        ->and($positions[1])->toBeLessThan($positions[2])
        ->and($positions[2])->toBeLessThan($positions[3]);
});

it('keeps the cms publication and footer flag rules', function () {
    $this->rules->update(['is_published' => false]);

    expect(storedFooterHtml($this, 'en'))->not->toContain('Community Rules');

    $this->rules->update(['is_published' => true, 'show_in_footer' => false]);

    expect(storedFooterHtml($this, 'en'))->not->toContain('Community Rules');
});

it('does not overwrite footer labels when the cms page is saved', function () {
    app(PageMenuManager::class)->save($this->about, [
        'title' => 'Совсем другое название',
        'title_en' => 'Completely different title',
        'show_in_footer' => true,
        'show_in_menu' => false,
        'menu_order' => 3,
    ]);

    expect(storedFooterHtml($this, 'en'))
        ->toContain('About the Community')
        ->not->toContain('Completely different title');
});

it('keeps the other footer items localized', function () {
    $en = storedFooterHtml($this, 'en');
    $ru = storedFooterHtml($this, 'ru');

    expect($en)->toContain('Contacts')->toContain('Log in')
        ->and($ru)->toContain('Контакты')->toContain('Авторизация');
});

it('keeps the english language when navigating to the cms page', function () {
    $this->withSession(['locale' => 'en'])->get('/o-soobschestve')
        ->assertOk()
        ->assertSee('About the Community')
        ->assertSee('Community Rules')
        ->assertDontSee('Русский текст о сообществе');
});

it('keeps the expected header structure', function () {
    $this->withSession(['locale' => 'en'])->get('/')
        ->assertOk()
        ->assertSee('<header>', false)
        ->assertSee('class="nav-container"', false)
        ->assertSee('class="nav-links"', false)
        ->assertSee('class="header-right"', false);
});
