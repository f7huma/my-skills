<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** أعمال التقرير المنظَّمة (نوع العمل وحقوله) وخيار عكس أعمال المنصة تلقائيًا — مطفأ افتراضيًا */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_report_items', function (Blueprint $t) {
            $t->string('kind', 40)->nullable();
            $t->json('fields')->nullable();
        });
        Schema::table('client_reports', fn (Blueprint $t) => $t->boolean('auto_sync')->default(false));
    }

    public function down(): void
    {
        Schema::table('client_report_items', fn (Blueprint $t) => $t->dropColumn(['kind', 'fields']));
        Schema::table('client_reports', fn (Blueprint $t) => $t->dropColumn('auto_sync'));
    }
};
