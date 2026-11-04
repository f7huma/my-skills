<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

if (! function_exists('today_local')) {
    /** اليوم الحالي بتوقيت الرياض كتاريخ محلي مستقل (بدون وقت) */
    function today_local(): Carbon
    {
        return Carbon::now(config('office.display_timezone'))->startOfDay();
    }
}

if (! function_exists('local_date')) {
    function local_date(?\DateTimeInterface $dt): ?string
    {
        if (! $dt) {
            return null;
        }

        return CarbonImmutable::instance($dt)->setTimezone(config('office.display_timezone'))->toDateString();
    }
}
