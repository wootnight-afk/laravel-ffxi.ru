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

        // Symfony's test request factory injects a fake `Accept-Language:
        // en-us,en;q=0.5` header. The interface is Russian by default
        // (ADR-011 §2.2), so tests declare a Russian browser explicitly;
        // locale tests override this header when they need another value.
        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9');
    }
}
