<?php

use App\Services\MetaWhatsAppCloudApiService;
use Illuminate\Support\Facades\Http;

it('sends text message using meta whatsapp cloud api', function () {
    config()->set('services.meta_whatsapp.api_version', 'v23.0');
    config()->set('services.meta_whatsapp.access_token', 'meta-token-001');
    config()->set('services.meta_whatsapp.phone_number_id', '1234567890');

    Http::fake([
        'https://graph.facebook.com/v23.0/1234567890/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [
                ['input' => '628111222333'],
            ],
            'messages' => [
                ['id' => 'wamid.HBgM...'],
            ],
        ], 200),
    ]);

    $service = new MetaWhatsAppCloudApiService;
    $result = $service->sendTextMessage('08111222333', 'Halo dari Rafen');

    expect($result['ok'])->toBeTrue()
        ->and($result['status'])->toBe(200)
        ->and($result['recipient'])->toBe('628111222333');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer meta-token-001')
            && data_get($request->data(), 'to') === '628111222333'
            && data_get($request->data(), 'text.body') === 'Halo dari Rafen';
    });
});

it('returns failed response when meta cloud api is not configured', function () {
    config()->set('services.meta_whatsapp.access_token', '');
    config()->set('services.meta_whatsapp.phone_number_id', '');

    Http::fake();

    $service = new MetaWhatsAppCloudApiService;
    $result = $service->sendTextMessage('08111222333', 'Halo');

    expect($result['ok'])->toBeFalse()
        ->and($result['status'])->toBe(0);

    Http::assertNothingSent();
});
