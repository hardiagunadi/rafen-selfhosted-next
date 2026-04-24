<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_blast_logs')) {
            return;
        }

        Schema::table('wa_blast_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_blast_logs', 'provider')) {
                $table->string('provider', 20)->nullable()->after('event')->index();
            }

            if (! Schema::hasColumn('wa_blast_logs', 'provider_message_id')) {
                $table->string('provider_message_id', 255)->nullable()->after('ref_id');
            }

            if (! Schema::hasColumn('wa_blast_logs', 'delivery_status')) {
                $table->string('delivery_status', 50)->nullable()->after('provider_message_id');
            }

            if (! Schema::hasColumn('wa_blast_logs', 'pricing_metadata')) {
                $table->json('pricing_metadata')->nullable()->after('delivery_status');
            }

            if (! Schema::hasColumn('wa_blast_logs', 'template_name')) {
                $table->string('template_name', 255)->nullable()->after('pricing_metadata');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_blast_logs')) {
            return;
        }

        Schema::table('wa_blast_logs', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('wa_blast_logs', 'provider') ? 'provider' : null,
                Schema::hasColumn('wa_blast_logs', 'provider_message_id') ? 'provider_message_id' : null,
                Schema::hasColumn('wa_blast_logs', 'delivery_status') ? 'delivery_status' : null,
                Schema::hasColumn('wa_blast_logs', 'pricing_metadata') ? 'pricing_metadata' : null,
                Schema::hasColumn('wa_blast_logs', 'template_name') ? 'template_name' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
