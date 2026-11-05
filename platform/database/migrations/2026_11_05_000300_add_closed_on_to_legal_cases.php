<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// تاريخ انتهاء القضية (للقضايا المنتهية المُدخلة لاحقًا لحصر أعمال العميل)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_cases', function (Blueprint $t) {
            $t->date('closed_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('legal_cases', function (Blueprint $t) {
            $t->dropColumn('closed_on');
        });
    }
};
