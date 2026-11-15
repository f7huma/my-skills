<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** عمل التقرير المرتبط بقضية أو طلب تنفيذ يُسجَّل في صفحته مرة واحدة عند النشر */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_report_items', fn (Blueprint $t) => $t->timestampTz('reflected_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('client_report_items', fn (Blueprint $t) => $t->dropColumn('reflected_at'));
    }
};
