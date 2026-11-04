<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/** توليد مراجع داخلية متسلسلة وآمنة مع التزامن (قفل استشاري في PostgreSQL) */
class References
{
    public static function next(string $prefix, string $table, string $column = 'reference'): string
    {
        $year = now(config('office.display_timezone'))->format('Y');
        $base = $prefix === 'CL' ? "{$prefix}-" : "{$prefix}-{$year}-";
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [crc32($table.$base)]);
        }
        $last = DB::table($table)->where($column, 'like', $base.'%')->orderByDesc($column)->value($column);
        $n = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
