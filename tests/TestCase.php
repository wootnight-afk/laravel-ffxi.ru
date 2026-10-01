<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF-защита бессмысленна в тестах и ломает POST (419).
        // Laravel 13 использует PreventRequestForgery вместо ValidateCsrfToken.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
