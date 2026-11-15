<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** المبالغ المحصّلة خلال فترة التقرير: مؤشر واحد يظهر فقط عند وجود تحصيل */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_reports', fn (Blueprint $t) => $t->json('collected')->nullable());
    }

    public function down(): void
    {
        Schema::table('client_reports', fn (Blueprint $t) => $t->dropColumn('collected'));
    }
};
