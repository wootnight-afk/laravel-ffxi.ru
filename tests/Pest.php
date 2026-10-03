<?php

use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Роли/разрешения и настройки нужны почти всем Feature-тестам.
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
