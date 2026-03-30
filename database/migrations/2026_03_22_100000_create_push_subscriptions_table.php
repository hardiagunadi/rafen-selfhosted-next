<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable'); // subscribable_type, subscribable_id
            $table->text('endpoint');
            $table->text('public_key');      // p256dh
            $table->string('auth_token', 64); // auth
            $table->unsignedBigInteger('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique('endpoint', 'push_sub_endpoint_unique');
            $table->index(['subscribable_type', 'subscribable_id'], 'push_sub_morphs_idx');
            $table->index('owner_id', 'push_sub_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
