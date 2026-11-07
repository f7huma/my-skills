<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// حسابات ينشئها المكتب مباشرة باسم مستخدم وكلمة مرور، دون بريد إلزامي (يبقى البريد فريدًا إن وُجد)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable(false)->change();
        });
    }
};
