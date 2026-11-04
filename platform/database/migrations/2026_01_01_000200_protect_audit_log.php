<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// سجل التدقيق للإضافة فقط: يمنع التعديل والحذف على مستوى قاعدة البيانات.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs; DROP FUNCTION IF EXISTS audit_logs_append_only();');
    }
};
