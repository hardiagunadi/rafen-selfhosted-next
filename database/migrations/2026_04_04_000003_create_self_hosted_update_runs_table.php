<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_hosted_update_runs', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->index();
            $table->string('action')->default('apply');
            $table->string('target_version')->nullable();
            $table->string('target_ref')->nullable();
            $table->string('target_commit')->nullable();
            $table->string('current_version')->nullable();
            $table->string('current_commit')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->index();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('output_excerpt')->nullable();
            $table->text('backup_path')->nullable();
            $table->string('rollback_ref')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_hosted_update_runs');
    }
};
