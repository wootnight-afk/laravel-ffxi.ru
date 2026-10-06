<?php

declare(strict_types=1);

it('reports that restore-test is not implemented yet', function () {
    $this->artisan('app:restore-test', ['id' => '00000000-0000-0000-0000-000000000000'])
        ->expectsOutputToContain('not implemented')
        ->assertFailed();
});
