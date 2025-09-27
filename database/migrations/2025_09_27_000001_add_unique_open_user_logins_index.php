<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only create the partial index on PostgreSQL where partial indexes are supported
        try {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS user_logins_unique_open_session ON user_logins (user_id, session_id) WHERE ended_at IS NULL;");
            }
        } catch (\Throwable $e) {
            // Ignore failures to avoid migration halt on unsupported DBs
        }
    }

    public function down(): void
    {
        try {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS user_logins_unique_open_session');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
