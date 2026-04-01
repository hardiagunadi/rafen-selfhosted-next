<?php

use App\Services\WaMultiSessionManager;

it('normalizes legacy pm2 home to a writable app storage path', function () {
    config()->set('wa.multi_session.pm2_home', '/var/www/.pm2');

    $manager = app(WaMultiSessionManager::class);
    $reflector = new ReflectionClass($manager);

    $primary = $reflector->getMethod('primaryPm2Home');
    $primary->setAccessible(true);

    $build = $reflector->getMethod('buildShellCommand');
    $build->setAccessible(true);

    $resolvedPm2Home = $primary->invoke($manager);
    $command = $build->invoke($manager, 'pm2 jlist', '/var/www/.pm2');

    expect($resolvedPm2Home)
        ->toBe(storage_path('.pm2'))
        ->and($command)->toContain('PM2_HOME='.storage_path('.pm2'))
        ->and($command)->not->toContain('PM2_HOME=/var/www/.pm2');
});
