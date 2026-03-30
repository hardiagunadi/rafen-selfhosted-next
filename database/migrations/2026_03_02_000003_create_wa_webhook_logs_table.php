<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);           // session | message
            $table->string('session_id')->nullable();   // device/session identifier
            $table->string('sender')->nullable();       // phone number sender (for message events)
            $table->text('message')->nullable();        // message body (for message events)
            $table->string('status')->nullable();       // online|offline (for session events)
            $table->json('payload');                    // full raw payload
            $table->timestamps();

            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_webhook_logs');
    }
};
