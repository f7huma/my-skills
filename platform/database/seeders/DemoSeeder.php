<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * بيانات اصطناعية للتطوير والمعاينة فقط — يرفض العمل في الإنتاج.
 * كلمات المرور هنا للبيئة المحلية والمعاينة فقط.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo-Pass-2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder is not allowed in production.');
        }
        $this->call(ReferenceDataSeeder::class);

        $lawyer = User::firstOrCreate(['email' => 'lawyer@example.test'], ['name' => 'المحامي (تجريبي)', 'password' => self::PASSWORD, 'role' => 'lawyer']);

        $ind = Client::firstOrCreate(['name' => 'سالم عبدالله التجريبي'], [
            'type' => 'individual', 'national_id' => '1000000001', 'phone' => '0500000001', 'email' => 'salem@example.test',
            'address' => 'الرياض — حي تجريبي', 'national_address' => 'RAAA1234', 'is_synthetic' => true,
        ]);
        $org = Client::firstOrCreate(['name' => 'شركة الأمثلة التجارية (تجريبية)'], [
            'type' => 'organization', 'unified_number' => '7000000001', 'commercial_registration' => '1010000001', 'phone' => '0110000001',
            'email' => 'info@example.test', 'address' => 'الرياض — طريق تجريبي', 'is_synthetic' => true,
        ]);
        $other = Client::firstOrCreate(['name' => 'نورة محمد التجريبية'], ['type' => 'individual', 'national_id' => '1000000002', 'is_synthetic' => true]);

        $mk = function (string $email, string $name, Client $c, bool $view, bool $claims) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'role' => 'client']);
            ClientMembership::firstOrCreate(['client_id' => $c->id, 'user_id' => $u->id], ['can_view' => $view, 'can_submit_claims' => $claims]);

            return $u;
        };
        $mk('salem@example.test', 'سالم (عميل فرد)', $ind, true, true);
        $mk('cfo@example.test', 'المدير المالي (شركة — متابعة ومطالبات)', $org, true, true);
        $mk('viewer@example.test', 'موظف (شركة — متابعة فقط)', $org, true, false);
        $mk('noura@example.test', 'نورة (عميل آخر)', $other, true, true);

        $this->call(DemoCasesSeeder::class, false, ['lawyer' => $lawyer, 'ind' => $ind, 'org' => $org, 'other' => $other]);
    }
}
