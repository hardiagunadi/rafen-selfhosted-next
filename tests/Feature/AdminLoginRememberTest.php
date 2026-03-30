<?php

it('auto-enables remember me for the admin pwa login screen', function () {
    $response = $this->get(route('login'));
    $cacheControl = (string) $response->headers->get('Cache-Control');

    $response->assertOk()
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT')
        ->assertSee('id="remember" name="remember"', false)
        ->assertSee('Remember Me', false)
        ->assertSee('function isStandaloneMode()', false)
        ->assertSee('function refreshIfRestoredFromCache(event)', false)
        ->assertSee('function syncRememberForPwa()', false)
        ->assertSee("window.addEventListener('pageshow', refreshIfRestoredFromCache);", false)
        ->assertSee("navigationEntry?.type === 'back_forward'", false)
        ->assertSee('window.location.reload();', false)
        ->assertSee('remember.checked = true;', false)
        ->assertSee('syncRememberForPwa();', false);

    expect($cacheControl)->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
});
