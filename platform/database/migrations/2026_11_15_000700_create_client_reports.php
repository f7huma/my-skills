<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// التقارير الدورية للعميل: مسودة يحررها المحامي ثم تُنشر لبوابة العميل بعد اعتماده
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('period', 10); // weekly | monthly | yearly
            $t->date('period_start');
            $t->date('period_end');
            $t->string('title', 200);
            $t->text('intro')->nullable();       // ملخص الفترة
            $t->text('next_steps')->nullable();  // الخطوات القادمة
            $t->json('matters');                 // لقطة القضايا والطلبات وقت الإعداد (قابلة للإخفاء)
            $t->string('status', 12)->default('draft'); // draft | published
            $t->timestampTz('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestampsTz();
            $t->index(['client_id', 'status', 'period_start']);
        });
        Schema::create('client_report_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_report_id')->constrained()->cascadeOnDelete();
            $t->string('matter_key', 40); // case:ID | execution:ID | general
            $t->date('occurred_on');
            $t->string('title', 300);
            $t->text('details')->nullable();
            $t->string('source', 10)->default('auto'); // auto | manual
            $t->timestampsTz();
            $t->index(['client_report_id', 'matter_key', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_report_items');
        Schema::dropIfExists('client_reports');
    }
};
