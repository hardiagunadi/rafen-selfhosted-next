<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chat_messages', function (Blueprint $table) {
            $table->string('media_type', 20)->nullable()->after('message'); // image/video/audio/document
            $table->string('media_path', 500)->nullable()->after('media_type');
            $table->string('media_mime', 100)->nullable()->after('media_path');
            $table->string('media_filename', 255)->nullable()->after('media_mime');
        });
    }

    public function down(): void
    {
        Schema::table('wa_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_path', 'media_mime', 'media_filename']);
        });
    }
};
