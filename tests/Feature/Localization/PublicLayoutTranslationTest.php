<?php

declare(strict_types=1);

use App\Models\News;
use App\Models\User;

function layoutNews(User $author, int $count): void
{
    for ($index = 0; $index < $count; $index++) {
        News::create([
            'user_id' => $author->id,
            'scope' => News::SCOPE_SITE,
            'title' => 'Layout news '.$index,
            'slug' => 'layout-news-'.$index.'-'.uniqid(),
            'body' => 'A sufficiently detailed news body.',
            'status' => News::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}

it('renders the public layout in english', function () {
    $this->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Game news')
        ->assertSee('Gallery')
        ->assertSee('Players')
        ->assertSee('Contacts')
        ->assertSee('Toggle theme')
        ->assertSee('Log in')
        ->assertSee('Interface language')
        ->assertSee('We use technical cookies required for the site')
        ->assertSee('Privacy policy')
        ->assertSee('Got it')
        ->assertDontSee('Новости игры')
        ->assertDontSee('Переключить тему')
        ->assertDontSee('Понятно');
});

it('renders the public layout in russian', function () {
    $this->withSession(['locale' => 'ru'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Новости игры')
        ->assertSee('Галерея')
        ->assertSee('Игроки')
        ->assertSee('Контакты')
        ->assertSee('Переключить тему')
        ->assertSee('Авторизация')
        ->assertSee('Язык интерфейса')
        ->assertSee('Политика ПД')
        ->assertSee('Понятно')
        ->assertDontSee('Game news')
        ->assertDontSee('Toggle theme');
});

it('renders the authenticated header actions in the active locale', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Log out')
        ->assertDontSee('Выход');
});

it('renders pagination labels in the active locale', function () {
    layoutNews(User::factory()->create(), 11);

    $this->withSession(['locale' => 'en'])
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Next');

    $this->withSession(['locale' => 'ru'])
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Вперёд');
});
