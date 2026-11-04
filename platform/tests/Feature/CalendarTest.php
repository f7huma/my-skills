<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Services\Calendar;
use App\Services\Workflow\HearingService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    public function test_business_days_skip_friday_saturday_and_holidays(): void
    {
        $cal = app(Calendar::class);
        // الخميس 2026-11-05 + 1 يوم عمل = الأحد 2026-11-08
        $this->assertSame('2026-11-08', $cal->add('2026-11-05', 1, 'business')->toDateString());
        // يوم البداية لا يُحتسب
        $this->assertSame('2026-11-02', $cal->add('2026-11-01', 1, 'business')->toDateString());
        $this->assertSame('2026-11-06', $cal->add('2026-11-01', 5, 'calendar')->toDateString());
        Holiday::create(['date' => '2026-11-03', 'name' => 'إجازة اختبار', 'kind' => 'office', 'reviewed' => true]);
        $cal2 = new Calendar;
        $this->assertSame('2026-11-09', $cal2->add('2026-11-01', 5, 'business')->toDateString());
        $this->assertFalse($cal2->isBusinessDay($cal2->date('2026-11-06')));
        $this->assertTrue($cal2->isBusinessDay($cal2->date('2026-11-08')));
    }

    public function test_year_and_month_boundaries(): void
    {
        $cal = app(Calendar::class);
        $this->assertSame('2027-01-04', $cal->add('2026-12-31', 2, 'business')->toDateString()); // الخميس ← الأحد ثم الاثنين
        $this->assertSame('2027-03-01', $cal->add('2027-02-28', 1)->toDateString());
        $this->assertSame('2028-02-29', $cal->add('2028-02-28', 1)->toDateString());
    }

    public function test_remaining_days_calendar_and_business(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00', 'Asia/Riyadh'));
        $cal = app(Calendar::class);
        $this->assertSame(4, $cal->remaining('2026-11-08'));
        $this->assertSame(2, $cal->remaining('2026-11-08', 'business')); // الخميس والأحد
        $this->assertSame(-2, $cal->remaining('2026-11-02'));
        Carbon::setTestNow();
    }

    public function test_date_only_values_do_not_shift_across_timezones(): void
    {
        // الساعة 23:30 بتوقيت الرياض = 20:30 UTC — اليوم المحلي يبقى نفسه
        Carbon::setTestNow(Carbon::parse('2026-11-04 23:30', 'Asia/Riyadh'));
        $this->assertSame('2026-11-04', today_local()->toDateString());
        // بعد منتصف الليل في الرياض وقبله في UTC
        Carbon::setTestNow(Carbon::parse('2026-11-05 00:30', 'Asia/Riyadh'));
        $this->assertSame('2026-11-05', today_local()->toDateString());
        $this->assertSame('2026-11-04', now()->utc()->toDateString());
        Carbon::setTestNow();
        $utc = HearingService::parseLocal('2026-11-05 01:00');
        $this->assertSame('2026-11-04 22:00:00', $utc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05', local_date($utc));
    }

    public function test_hijri_umm_al_qura_rendering(): void
    {
        $h = app(Calendar::class)->hijri('2026-11-04');
        $this->assertStringContainsString('1448', $h);
        $this->assertStringEndsWith('هـ', $h);
    }
}
