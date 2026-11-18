<?php

use App\Models\Page;

beforeEach(function () {
    Page::query()->forceDelete();

    Page::query()->create([
        'title' => 'Правила сообщества',
        'title_en' => 'Community Rules',
        'slug' => 'pravila-soobschestva',
        'body' => "## Общие правила\n\n1. Уважайте других участников.\n2. Спам и реклама запрещены.",
        'body_en' => "## General rules\n\n1. Respect other members.\n2. Spam and advertising are prohibited.",
        'meta_title_en' => 'Community Rules',
        'meta_description_en' => 'Community rules: respect for other members, no spam or advertising.',
        'is_published' => true,
    ]);

    Page::query()->create([
        'title' => 'О сообществе',
        'title_en' => 'About the Community',
        'slug' => 'o-soobschestve',
        'body' => 'Содружество русскоязычных игроков Final Fantasy XI — **Phoenix Server**.',
        'body_en' => 'A community of Russian-speaking Final Fantasy XI players — **Phoenix Server**.',
        'meta_title_en' => 'About the Community',
        'meta_description_en' => 'The Russian-speaking Final Fantasy XI community on Phoenix Server.',
        'is_published' => true,
    ]);
});

it('shows the russian rules for the russian locale', function () {
    $this->withSession(['locale' => 'ru'])
        ->get('/pravila-soobschestva')
        ->assertOk()
        ->assertSee('Правила сообщества')
        ->assertSee('Уважайте других участников')
        ->assertDontSee('Respect other members');
});

it('shows the english rules for the english locale', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/pravila-soobschestva')
        ->assertOk()
        ->assertSee('Community Rules')
        ->assertSee('Respect other members')
        ->assertSee('Spam and advertising are prohibited')
        ->assertDontSee('Уважайте других участников')
        ->assertDontSee('Спам и реклама запрещены');
});

it('shows the english about page for the english locale', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/o-soobschestve')
        ->assertOk()
        ->assertSee('About the Community')
        ->assertSee('A community of Russian-speaking Final Fantasy XI players')
        ->assertDontSee('Содружество русскоязычных игроков');
});

it('keeps the russian content untouched on the russian about page', function () {
    $this->withSession(['locale' => 'ru'])
        ->get('/o-soobschestve')
        ->assertOk()
        ->assertSee('О сообществе')
        ->assertSee('Содружество русскоязычных игроков')
        ->assertDontSee('A community of Russian-speaking Final Fantasy XI players');
});

it('renders the english body as sanitized markdown html', function () {
    $page = Page::query()->where('slug', 'pravila-soobschestva')->firstOrFail();

    expect($page->body_html_en)
        ->toContain('<h2>General rules</h2>')
        ->toContain('<ol>');
});
