<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// حساب أُغلق نهائيًا بعد حذف آخر عضوية له: لا يدخل ولا يظهر في القوائم، ويبقى أثره في سجل التدقيق
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestampTz('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('closed_at');
        });
    }
};
