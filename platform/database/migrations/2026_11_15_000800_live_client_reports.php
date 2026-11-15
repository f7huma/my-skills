<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** التقرير المفتوح: يلتقط كل إجراء جديد تلقائيًا حتى يُغلق، ولا يعيد ما حذفه المحامي */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_report_items', function (Blueprint $t) {
            $t->string('source_ref', 40)->nullable()->index();
        });
        Schema::table('client_reports', function (Blueprint $t) {
            $t->json('dismissed_refs')->nullable();
            $t->timestampTz('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_report_items', fn (Blueprint $t) => $t->dropColumn('source_ref'));
        Schema::table('client_reports', fn (Blueprint $t) => $t->dropColumn(['dismissed_refs', 'closed_at']));
    }
};
