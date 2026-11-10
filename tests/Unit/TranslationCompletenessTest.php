<?php

declare(strict_types=1);

use App\Support\TranslationCompleteness;
use Illuminate\Support\Facades\File;

function translationFixture(string $root, string $locale, string $file, string $contents): void
{
    File::ensureDirectoryExists($root.'/'.$locale.'/'.dirname($file));
    File::put($root.'/'.$locale.'/'.$file, $contents);
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/ffxi-translations-*', GLOB_ONLYDIR) ?: [] as $directory) {
        File::deleteDirectory($directory);
    }
});

it('reports keys missing in either locale', function () {
    $root = sys_get_temp_dir().'/ffxi-translations-'.bin2hex(random_bytes(6));

    translationFixture($root, 'ru', 'site.php', "<?php\n\nreturn ['a' => 'A', 'nested' => ['b' => 'B']];\n");
    translationFixture($root, 'en', 'site.php', "<?php\n\nreturn ['nested' => ['b' => 'B'], 'c' => 'C'];\n");

    $result = app(TranslationCompleteness::class)->compare($root);

    expect($result['missing_in_target'])->toBe(['site.a'])
        ->and($result['missing_in_reference'])->toBe(['site.c']);
});

it('flattens nested directories into namespaces', function () {
    $root = sys_get_temp_dir().'/ffxi-translations-'.bin2hex(random_bytes(6));

    translationFixture($root, 'ru', 'admin/users.php', "<?php\n\nreturn ['title' => 'Users'];\n");
    translationFixture($root, 'en', 'admin/users.php', "<?php\n\nreturn [];\n");
    translationFixture($root, 'ru', 'notes.txt', 'ignored');
    translationFixture($root, 'en', 'notes.txt', 'ignored');

    $result = app(TranslationCompleteness::class)->compare($root);

    expect($result['missing_in_target'])->toBe(['admin.users.title']);
});

it('treats a missing target locale as empty', function () {
    $root = sys_get_temp_dir().'/ffxi-translations-'.bin2hex(random_bytes(6));

    translationFixture($root, 'ru', 'site.php', "<?php\n\nreturn ['a' => 'A'];\n");

    $result = app(TranslationCompleteness::class)->compare($root);

    expect($result['missing_in_target'])->toBe(['site.a'])
        ->and($result['missing_in_reference'])->toBe([]);
});

it('passes the completeness check for the project translations', function () {
    $result = app(TranslationCompleteness::class)->compare(lang_path());

    expect($result['missing_in_target'])->toBe([]);
});

it('exits successfully on the project translations', function () {
    $this->artisan('app:translations:check')->assertSuccessful();
});

it('fails when the target locale misses keys', function () {
    $root = sys_get_temp_dir().'/ffxi-translations-'.bin2hex(random_bytes(6));

    translationFixture($root, 'ru', 'site.php', "<?php\n\nreturn ['a' => 'A'];\n");
    translationFixture($root, 'en', 'site.php', "<?php\n\nreturn [];\n");

    $this->artisan('app:translations:check', ['--path' => $root])
        ->expectsOutputToContain('site.a')
        ->assertFailed();
});

it('fails when the locale directory does not exist', function () {
    $this->artisan('app:translations:check', ['--path' => '/nonexistent/locales'])
        ->assertFailed();
});
