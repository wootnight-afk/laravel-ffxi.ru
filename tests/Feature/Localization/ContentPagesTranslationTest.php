<?php

declare(strict_types=1);

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Album;
use App\Models\News;
use App\Models\Photo;
use App\Models\User;
use App\Services\HomePageContent;
use App\Services\NewsPageContent;
use App\Services\SettingsRepository;
use Livewire\Livewire;

it('loads both CMS body translations into the editor without substitution', function (string $contentClass, string $recordKey) {
    $settings = app(SettingsRepository::class);
    $settings->setMany([
        $contentClass::KEY_BODY => 'Russian **original** body.',
        $contentClass::KEY_BODY_EN => 'English **original** body.',
        '' => 'An unrelated empty-key setting.',
    ]);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    foreach (['ru', 'en'] as $locale) {
        app()->setLocale($locale);

        expect(app($contentClass)->editable()['body'])->toBe('Russian **original** body.')
            ->and(app($contentClass)->editable()['body_en'])->toBe('English **original** body.');

        Livewire::actingAs($admin)->test(EditPage::class, ['record' => $recordKey])
            ->assertSet('data.body', 'Russian **original** body.')
            ->assertSet('data.body_en', 'English **original** body.');
    }
})->with([
    'home' => [HomePageContent::class, 'sys.home'],
    'news' => [NewsPageContent::class, 'sys.news.index'],
]);

it('preserves the other CMS body translation when saving either language', function (string $contentClass, string $recordKey, string $locale) {
    $settings = app(SettingsRepository::class);
    $settings->setMany([
        $contentClass::KEY_BODY => 'Russian original body.',
        $contentClass::KEY_BODY_EN => 'English original body.',
    ]);

    $admin = User::factory()->create(['email_verified_at' => now(), 'locale' => $locale]);
    $admin->assignRole('admin');

    $field = $locale === 'ru' ? 'body' : 'body_en';
    $ru = $locale === 'ru' ? 'Updated Russian body.' : 'Russian original body.';
    $en = $locale === 'en' ? 'Updated English body.' : 'English original body.';

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => $recordKey])
        ->set('data.'.$field, $locale === 'ru' ? $ru : $en)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('data.body', $ru)
        ->assertSet('data.body_en', $en);

    expect($settings->get($contentClass::KEY_BODY))->toBe($ru)
        ->and($settings->get($contentClass::KEY_BODY_EN))->toBe($en);

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => $recordKey])
        ->assertSet('data.body', $ru)
        ->assertSet('data.body_en', $en);
})->with([
    'home RU' => [HomePageContent::class, 'sys.home', 'ru'],
    'home EN' => [HomePageContent::class, 'sys.home', 'en'],
    'news RU' => [NewsPageContent::class, 'sys.news.index', 'ru'],
    'news EN' => [NewsPageContent::class, 'sys.news.index', 'en'],
]);

it('keeps a missing CMS english body empty in the editor and falls back on display', function (string $contentClass, bool $storeEnglish, ?string $englishBody) {
    $settings = app(SettingsRepository::class);
    $settings->set($contentClass::KEY_BODY, 'Russian **fallback** body.');

    if ($storeEnglish) {
        $settings->set($contentClass::KEY_BODY_EN, $englishBody);
    }

    app()->setLocale('en');
    $content = app($contentClass);

    expect($content->editable()['body'])->toBe('Russian **fallback** body.')
        ->and($content->editable()['body_en'])->toBe($englishBody ?? '')
        ->and($content->view()['bodyHtml'])->toContain('Russian <strong>fallback</strong> body.');
})->with([
    'home missing' => [HomePageContent::class, false, null],
    'home null' => [HomePageContent::class, true, null],
    'home empty' => [HomePageContent::class, true, ''],
    'home blank' => [HomePageContent::class, true, '   '],
    'news missing' => [NewsPageContent::class, false, null],
    'news null' => [NewsPageContent::class, true, null],
    'news empty' => [NewsPageContent::class, true, ''],
    'news blank' => [NewsPageContent::class, true, '   '],
]);

it('retains complete multiline news header translations after refresh save and reopening', function () {
    $ru = "Первый **русский** абзац.\n\nВторой русский абзац.\nПродолжение текста.";
    $en = "First **English** paragraph.\n\nSecond English paragraph.\nContinuation of the text.";
    $settings = app(SettingsRepository::class);
    $settings->setMany([
        NewsPageContent::KEY_BODY => $ru,
        NewsPageContent::KEY_BODY_EN => $en,
    ]);
    $before = $settings->all();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $editor = Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.news.index']);

    foreach (['ru', 'en', 'ru'] as $locale) {
        app()->setLocale($locale);
        $editor->call('$refresh')
            ->assertSet('data.body', $ru)
            ->assertSet('data.body_en', $en);
    }

    $editor->call('save')->assertHasNoErrors()
        ->assertSet('data.body', $ru)
        ->assertSet('data.body_en', $en);

    foreach ($before as $key => $value) {
        expect($settings->get($key))->toBe($value);
    }

    Livewire::actingAs($admin)->test(EditPage::class, ['record' => 'sys.news.index'])
        ->assertSet('data.body', $ru)
        ->assertSet('data.body_en', $en);
});

function contentNews(User $author, array $overrides = []): News
{
    return News::create(array_merge([
        'user_id' => $author->id,
        'scope' => News::SCOPE_SITE,
        'title' => 'Localized news',
        'slug' => 'localized-news-'.uniqid(),
        'body' => 'A sufficiently detailed news body.',
        'status' => News::STATUS_PUBLISHED,
        'published_at' => now(),
    ], $overrides));
}

function contentAlbum(User $author, array $overrides = []): Album
{
    return Album::create(array_merge([
        'user_id' => $author->id,
        'scope' => Album::SCOPE_SITE,
        'title' => 'Localized album',
        'slug' => 'localized-album-'.uniqid(),
        'is_published' => true,
    ], $overrides));
}

function contentPhoto(Album $album, User $author, array $overrides = []): Photo
{
    return Photo::create(array_merge([
        'album_id' => $album->id,
        'user_id' => $author->id,
        'path_original' => 'gallery/original.webp',
        'path_medium' => 'gallery/medium.webp',
        'path_thumb' => 'gallery/thumb.webp',
        'is_published' => true,
    ], $overrides));
}

it('renders the news list in english', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('No news yet. Check back later.')
        ->assertDontSee('Пока новостей нет. Заходите позже.');
});

it('renders the news article chrome in english', function () {
    $news = contentNews(User::factory()->create(), ['comments_enabled' => true]);

    $this->withSession(['locale' => 'en'])
        ->get(route('news.show', $news->slug))
        ->assertOk()
        ->assertSee('FFXI.ru editorial team')
        ->assertSee('All news')
        ->assertSee('Comments')
        ->assertSee('to leave a comment.')
        ->assertDontSee('Редакция FFXI.ru')
        ->assertDontSee('Ко всем новостям');
});

it('renders the gallery in english', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('Gallery')
        ->assertSee('No albums yet. Check back later.')
        ->assertDontSee('Пока альбомов нет. Заходите позже.');
});

it('renders an empty album in english', function () {
    $album = contentAlbum(User::factory()->create());

    $this->withSession(['locale' => 'en'])
        ->get(route('gallery.index'))
        ->assertOk()
        ->assertSee('There are no photos in this section yet.');

    $this->withSession(['locale' => 'en'])
        ->get(route('gallery.album', $album->slug))
        ->assertOk()
        ->assertSee('There are no photos in this album yet.')
        ->assertSee('All albums')
        ->assertDontSee('В этом альбоме пока нет фотографий.');
});

it('renders the photo page in english', function () {
    $author = User::factory()->create();
    $album = contentAlbum($author);
    contentPhoto($album, $author);
    $photo = contentPhoto($album, $author, ['taken_at' => now()]);
    contentPhoto($album, $author);

    $this->withSession(['locale' => 'en'])
        ->get(route('gallery.photo', [$album->slug, $photo->id]))
        ->assertOk()
        ->assertSee('Back to album')
        ->assertSee('Taken:')
        ->assertSee('Previous')
        ->assertSee('Next')
        ->assertSee('to leave a comment.')
        ->assertDontSee('Снято:');
});

it('renders the russian news and gallery chrome', function () {
    $author = User::factory()->create();
    $album = contentAlbum($author);
    $photo = contentPhoto($album, $author);

    $this->withSession(['locale' => 'ru'])
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Пока новостей нет. Заходите позже.');

    $this->withSession(['locale' => 'ru'])
        ->get(route('gallery.photo', [$album->slug, $photo->id]))
        ->assertOk()
        ->assertSee('К альбому')
        ->assertSee('чтобы оставить комментарий.')
        ->assertDontSee('Back to album');
});
