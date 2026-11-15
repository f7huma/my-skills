<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** شعار العميل: يظهر في تقاريره بجانب اسمه. يُحفظ على خادم المكتب فقط */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->string('logo_path', 120)->nullable());
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('logo_path'));
    }
};
