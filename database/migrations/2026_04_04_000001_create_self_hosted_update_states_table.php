<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_hosted_update_states', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->unique();
            $table->string('current_version')->nullable();
            $table->string('current_commit')->nullable();
            $table->string('latest_version')->nullable();
            $table->string('latest_commit')->nullable();
            $table->timestamp('latest_published_at')->nullable();
            $table->text('latest_manifest_url')->nullable();
            $table->text('latest_release_notes_url')->nullable();
            $table->boolean('update_available')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status')->nullable();
            $table->text('last_check_message')->nullable();
            $table->json('manifest_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_hosted_update_states');
    }
};
