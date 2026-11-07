<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// اسم مستخدم اختياري للدخول بدل البريد (يقبل المسافات). فريد دون اعتبار حالة الأحرف.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 60)->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX users_username_lower_unique ON users (lower(username)) WHERE username IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_username_lower_unique');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('username');
        });
    }
};
