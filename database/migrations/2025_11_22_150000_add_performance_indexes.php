<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
	public function up(): void
	{
		// Appointments: composite indexes for common query patterns
		if (Schema::hasTable('appointments')) {
			Schema::table('appointments', function (Blueprint $table) {
				// Ensure columns exist before indexing (defensive)
				if (Schema::hasColumn('appointments','professional_id') && Schema::hasColumn('appointments','start')) {
					$table->index(['professional_id','start'],'appointments_professional_start_idx');
				}
				if (Schema::hasColumn('appointments','patient_id') && Schema::hasColumn('appointments','start')) {
					$table->index(['patient_id','start'],'appointments_patient_start_idx');
				}
				if (Schema::hasColumn('appointments','status') && Schema::hasColumn('appointments','start')) {
					$table->index(['status','start'],'appointments_status_start_idx');
				}
			});
		}

		// Appointment session logs: add composite index including session for faster filtering; created_at already implicit in timestamps but add explicit if needed
		if (Schema::hasTable('appointment_session_logs')) {
			Schema::table('appointment_session_logs', function (Blueprint $table) {
				if (!Schema::hasColumn('appointment_session_logs','appointment_session_id')) return;
				$table->index(['appointment_session_id','event_type'],'appointment_session_logs_session_event_idx');
				// Optional created_at index for time-range queries
				if (Schema::hasColumn('appointment_session_logs','created_at')) {
					$table->index(['created_at'],'appointment_session_logs_created_at_idx');
				}
			});
			// PostgreSQL partial index to accelerate metrics_summary lookups (optional)
			try {
				if (DB::getDriverName() === 'pgsql') {
					DB::statement("CREATE INDEX IF NOT EXISTS appointment_session_logs_metrics_summary_idx ON appointment_session_logs (appointment_session_id) WHERE event_type = 'metrics_summary';");
				}
			} catch (\Throwable $e) { /* ignore */ }
		}

		// Appointment sessions: index started_at for range scans by date
		if (Schema::hasTable('appointment_sessions')) {
			Schema::table('appointment_sessions', function (Blueprint $table) {
				if (Schema::hasColumn('appointment_sessions','started_at')) {
					$table->index('started_at','appointment_sessions_started_at_idx');
				}
			});
		}

		// Daily metrics: add plain index on day for range queries (unique exists but explicit index keeps consistency on some engines)
		if (Schema::hasTable('appointment_metrics_daily')) {
			Schema::table('appointment_metrics_daily', function (Blueprint $table) {
				if (Schema::hasColumn('appointment_metrics_daily','day')) {
					$table->index('day','appointment_metrics_daily_day_idx');
				}
			});
		}

		// User logins: composite index on user + started_at (partial open-session index already handled in original create).
		if (Schema::hasTable('user_logins')) {
			Schema::table('user_logins', function (Blueprint $table) {
				if (Schema::hasColumn('user_logins','user_id') && Schema::hasColumn('user_logins','started_at')) {
					$table->index(['user_id','started_at'],'user_logins_user_started_idx');
				}
			});
		}
	}

	public function down(): void
	{
		// Drop indexes defensively (names must match those created above)
		if (Schema::hasTable('appointments')) {
			Schema::table('appointments', function (Blueprint $table) {
				$table->dropIndex('appointments_professional_start_idx');
				$table->dropIndex('appointments_patient_start_idx');
				$table->dropIndex('appointments_status_start_idx');
			});
		}
		if (Schema::hasTable('appointment_session_logs')) {
			Schema::table('appointment_session_logs', function (Blueprint $table) {
				$table->dropIndex('appointment_session_logs_session_event_idx');
				$table->dropIndex('appointment_session_logs_created_at_idx');
			});
			try { if (DB::getDriverName() === 'pgsql') { DB::statement("DROP INDEX IF EXISTS appointment_session_logs_metrics_summary_idx"); } } catch (\Throwable $e) { /* ignore */ }
		}
		if (Schema::hasTable('appointment_sessions')) {
			Schema::table('appointment_sessions', function (Blueprint $table) {
				$table->dropIndex('appointment_sessions_started_at_idx');
			});
		}
		if (Schema::hasTable('appointment_metrics_daily')) {
			Schema::table('appointment_metrics_daily', function (Blueprint $table) {
				$table->dropIndex('appointment_metrics_daily_day_idx');
			});
		}
		if (Schema::hasTable('user_logins')) {
			Schema::table('user_logins', function (Blueprint $table) {
				$table->dropIndex('user_logins_user_started_idx');
			});
		}
	}
};
