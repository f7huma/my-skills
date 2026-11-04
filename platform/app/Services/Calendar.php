<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use IntlDateFormatter;

/**
 * الحساب الإجرائي بتوقيت الرياض. التواريخ هنا "تواريخ محلية" مستقلة لا تحمل وقتًا.
 * أيام العمل تستبعد الجمعة والسبت وأيام تقويم الإجازات المعتمد.
 */
class Calendar
{
    /** @var array<string,bool>|null */
    private ?array $holidays = null;

    public function date(string|\DateTimeInterface $d): CarbonImmutable
    {
        $s = $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr($d, 0, 10);

        return CarbonImmutable::createFromFormat('!Y-m-d', $s, config('office.display_timezone'));
    }

    public function isWeekend(CarbonImmutable $d): bool
    {
        return in_array($d->isoWeekday(), config('office.weekend_days'), true);
    }

    public function isHoliday(CarbonImmutable $d): bool
    {
        $this->holidays ??= Holiday::query()->pluck('date')->mapWithKeys(fn ($x) => [$x->format('Y-m-d') => true])->all();

        return isset($this->holidays[$d->format('Y-m-d')]);
    }

    public function isBusinessDay(CarbonImmutable $d): bool
    {
        return ! $this->isWeekend($d) && ! $this->isHoliday($d);
    }

    /**
     * يضيف مدة إلى تاريخ بداية.
     * calendar: اليوم الأخير = البداية + N يومًا.
     * business: تُعدّ أيام العمل التالية لتاريخ البداية (لا يُحتسب يوم البداية)، فاليوم الأخير هو يوم العمل رقم N.
     */
    public function add(string|\DateTimeInterface $start, int $days, string $type = 'calendar'): CarbonImmutable
    {
        $d = $this->date($start);
        if ($type === 'calendar') {
            return $d->addDays($days);
        }
        $step = $days >= 0 ? 1 : -1;
        $left = abs($days);
        while ($left > 0) {
            $d = $d->addDays($step);
            if ($this->isBusinessDay($d)) {
                $left--;
            }
        }

        return $d;
    }

    /** إذا وقع آخر يوم في عطلة يُنقل لأول يوم عمل بعده — يُستخدم فقط إذا نصّت القاعدة على ذلك */
    public function rollForward(CarbonImmutable $d): CarbonImmutable
    {
        while (! $this->isBusinessDay($d)) {
            $d = $d->addDay();
        }

        return $d;
    }

    /** المتبقي بالأيام (سالب = متأخر) من اليوم المحلي */
    public function remaining(string|\DateTimeInterface $end, string $type = 'calendar'): int
    {
        $today = $this->date(today_local());
        $end = $this->date($end);
        if ($type === 'calendar') {
            return (int) $today->diffInDays($end, false);
        }
        $sign = $end->gte($today) ? 1 : -1;
        $count = 0;
        $d = $today;
        while (! $d->equalTo($end)) {
            $d = $d->addDays($sign);
            if ($this->isBusinessDay($d)) {
                $count += $sign;
            }
        }

        return $count;
    }

    public function hijri(string|\DateTimeInterface|null $d): ?string
    {
        if (! $d) {
            return null;
        }
        $fmt = new IntlDateFormatter('ar_SA@calendar=islamic-umalqura;numbers=latn', IntlDateFormatter::LONG, IntlDateFormatter::NONE,
            config('office.display_timezone'), IntlDateFormatter::TRADITIONAL, 'd MMMM y');

        return $fmt->format($this->date($d)->setTime(12, 0)).' هـ';
    }
}
