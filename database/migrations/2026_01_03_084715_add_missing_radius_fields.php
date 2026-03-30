<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mikrotik_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('mikrotik_connections', 'name')) {
                $table->string('name')->after('id');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'host')) {
                $table->string('host')->after('name');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'api_port')) {
                $table->unsignedSmallInteger('api_port')->default(8728)->after('host');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'api_ssl_port')) {
                $table->unsignedSmallInteger('api_ssl_port')->default(8729)->after('api_port');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'use_ssl')) {
                $table->boolean('use_ssl')->default(false)->after('api_ssl_port');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'username')) {
                $table->string('username')->after('use_ssl');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'password')) {
                $table->string('password')->after('username');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'radius_secret')) {
                $table->string('radius_secret')->nullable()->after('password');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'ros_version')) {
                $table->string('ros_version')->default('7')->after('radius_secret');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'api_timeout')) {
                $table->unsignedTinyInteger('api_timeout')->default(10)->after('ros_version');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'notes')) {
                $table->text('notes')->nullable()->after('api_timeout');
            }
            if (! Schema::hasColumn('mikrotik_connections', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
        });

        Schema::table('radius_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('radius_accounts', 'mikrotik_connection_id')) {
                $table->foreignId('mikrotik_connection_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('radius_accounts', 'username')) {
                $table->string('username')->unique()->after('mikrotik_connection_id');
            }
            if (! Schema::hasColumn('radius_accounts', 'password')) {
                $table->string('password')->after('username');
            }
            if (! Schema::hasColumn('radius_accounts', 'service')) {
                $table->enum('service', ['pppoe', 'hotspot'])->after('password');
            }
            if (! Schema::hasColumn('radius_accounts', 'ipv4_address')) {
                $table->string('ipv4_address')->nullable()->after('service');
            }
            if (! Schema::hasColumn('radius_accounts', 'rate_limit')) {
                $table->string('rate_limit')->nullable()->after('ipv4_address');
            }
            if (! Schema::hasColumn('radius_accounts', 'profile')) {
                $table->string('profile')->nullable()->after('rate_limit');
            }
            if (! Schema::hasColumn('radius_accounts', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('profile');
            }
            if (! Schema::hasColumn('radius_accounts', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
            if (! $this->indexExists('radius_accounts', 'radius_accounts_service_is_active_index')) {
                $table->index(['service', 'is_active']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('radius_accounts', function (Blueprint $table) {
            if ($this->foreignKeyExists('radius_accounts', 'radius_accounts_mikrotik_connection_id_foreign')) {
                $table->dropForeign('radius_accounts_mikrotik_connection_id_foreign');
            }
            foreach (['mikrotik_connection_id', 'username', 'password', 'service', 'ipv4_address', 'rate_limit', 'profile', 'is_active', 'notes'] as $column) {
                if (Schema::hasColumn('radius_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
            if ($this->indexExists('radius_accounts', 'radius_accounts_service_is_active_index')) {
                $table->dropIndex('radius_accounts_service_is_active_index');
            }
        });

        Schema::table('mikrotik_connections', function (Blueprint $table) {
            foreach (['name', 'host', 'api_port', 'api_ssl_port', 'use_ssl', 'username', 'password', 'radius_secret', 'ros_version', 'api_timeout', 'notes', 'is_active'] as $column) {
                if (Schema::hasColumn('mikrotik_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::getDatabaseName();
        $tableName = DB::getTablePrefix().$table;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $result = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
                [$database, $tableName, $foreignKeyName]
            );

            return $result !== null;
        }

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT conname FROM pg_constraint WHERE conname = ? LIMIT 1',
                [$foreignKeyName]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            // SQLite does not persist named constraints like MySQL/PostgreSQL.
            return false;
        }

        return false;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::getDatabaseName();
        $tableName = DB::getTablePrefix().$table;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $result = DB::selectOne(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                [$database, $tableName, $indexName]
            );

            return $result !== null;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$tableName}')");
            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1',
                [$tableName, $indexName]
            );

            return $result !== null;
        }

        return false;
    }
};
