<?php

it('renders privacy policy page', function () {
    $this->get(route('privacy-policy'))
        ->assertSuccessful()
        ->assertSee('Kebijakan Privasi Rafen');
});
