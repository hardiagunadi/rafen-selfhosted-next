<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('wa_webhook_logs');
    Schema::create('wa_webhook_logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('event_type', 50);
        $table->string('session_id')->nullable();
        $table->string('sender')->nullable();
        $table->text('message')->nullable();
        $table->string('status')->nullable();
        $table->json('payload');
        $table->timestamps();
    });
});

it('verifies meta webhook challenge with valid token', function () {
    config()->set('services.meta_whatsapp.webhook_verify_token', 'rafen-meta-token');

    $response = $this->get('/webhook/meta/whatsapp?hub.mode=subscribe&hub.verify_token=rafen-meta-token&hub.challenge=123456');

    $response->assertSuccessful();
    $response->assertSeeText('123456');
});

it('rejects meta webhook challenge with invalid token', function () {
    config()->set('services.meta_whatsapp.webhook_verify_token', 'rafen-meta-token');

    $response = $this->get('/webhook/meta/whatsapp?hub.mode=subscribe&hub.verify_token=salah&hub.challenge=123456');

    $response->assertForbidden();
});

it('stores incoming meta messages and statuses', function () {
    config()->set('services.meta_whatsapp.app_secret', '');

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => '123456789',
                'changes' => [
                    [
                        'field' => 'messages',
                        'value' => [
                            'metadata' => [
                                'display_phone_number' => '628123456789',
                                'phone_number_id' => 'phone-number-id-001',
                            ],
                            'messages' => [
                                [
                                    'from' => '6281211112222',
                                    'id' => 'wamid.message.001',
                                    'timestamp' => '1710000000',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'Halo Rafen',
                                    ],
                                ],
                            ],
                            'statuses' => [
                                [
                                    'id' => 'wamid.status.001',
                                    'status' => 'delivered',
                                    'recipient_id' => '6281211112222',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/webhook/meta/whatsapp', $payload);

    $response->assertSuccessful()->assertJson(['status' => true]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'meta_message',
        'session_id' => 'phone-number-id-001',
        'sender' => '6281211112222',
        'message' => 'Halo Rafen',
        'status' => 'text',
    ]);

    $this->assertDatabaseHas('wa_webhook_logs', [
        'event_type' => 'meta_status',
        'session_id' => 'phone-number-id-001',
        'sender' => '6281211112222',
        'message' => 'wamid.status.001',
        'status' => 'delivered',
    ]);
});

it('rejects payload when app secret signature is invalid', function () {
    config()->set('services.meta_whatsapp.app_secret', 'meta-app-secret');

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'changes' => [
                    [
                        'field' => 'messages',
                        'value' => [
                            'metadata' => [
                                'phone_number_id' => 'phone-number-id-001',
                            ],
                            'messages' => [
                                [
                                    'from' => '6281211112222',
                                    'type' => 'text',
                                    'text' => ['body' => 'Halo'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->withHeaders([
        'X-Hub-Signature-256' => 'sha256=signature-salah',
    ])->postJson('/webhook/meta/whatsapp', $payload);

    $response->assertUnauthorized();
    $this->assertDatabaseCount('wa_webhook_logs', 0);
});
