<?php

use Illuminate\Support\Facades\Route;

it('does not register self-hosted license routes in saas mode by default', function () {
    putenv('LICENSE_SELF_HOSTED_ENABLED=false');
    $_ENV['LICENSE_SELF_HOSTED_ENABLED'] = 'false';
    $_SERVER['LICENSE_SELF_HOSTED_ENABLED'] = 'false';

    $this->refreshApplication();

    expect(Route::has('super-admin.settings.license'))->toBeFalse()
        ->and(Route::has('super-admin.settings.license.update'))->toBeFalse()
        ->and(Route::has('super-admin.settings.license.activation-request'))->toBeFalse();
});
