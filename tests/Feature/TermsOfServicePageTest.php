<?php

it('renders terms of service page', function () {
    $this->get(route('terms-of-service'))
        ->assertSuccessful()
        ->assertSee('Ketentuan Layanan Rafen');
});
