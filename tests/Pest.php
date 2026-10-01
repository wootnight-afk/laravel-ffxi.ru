<?php

use Database\Seeders\RoleAndPermissionSeeder;
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
        // Роли и разрешения нужны почти всем Feature-тестам.
        $this->seed(RoleAndPermissionSeeder::class);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
