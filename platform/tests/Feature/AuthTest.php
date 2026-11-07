<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Auth\InvitationService;
use Database\Seeders\DemoSeeder;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_and_role_redirects(): void
    {
        $this->demo();
        $this->post('/login', ['email' => 'lawyer@example.test', 'password' => DemoSeeder::PASSWORD])->assertRedirect(route('home'));
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'salem@example.test', 'password' => DemoSeeder::PASSWORD]);
        $this->get('/')->assertRedirect(route('portal.home'));
        $this->assertTrue(AuditLog::where('action', 'auth.login')->exists());
    }

    public function test_wrong_password_and_inactive_account_are_rejected(): void
    {
        $this->demo();
        $this->post('/login', ['email' => 'lawyer@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->user('salem@example.test')->forceFill(['is_active' => false])->save();
        $this->post('/login', ['email' => 'salem@example.test', 'password' => DemoSeeder::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->demo();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'lawyer@example.test', 'password' => 'bad']);
        }
        $this->post('/login', ['email' => 'lawyer@example.test', 'password' => DemoSeeder::PASSWORD])->assertStatus(429);
    }

    public function test_invitation_is_single_use_and_expires(): void
    {
        $this->demo();
        $client = $this->client('شركة الأمثلة');
        $res = app(InvitationService::class)->create(['email' => 'new@example.test', 'name' => 'مستخدم جديد', 'role' => 'client', 'client_id' => $client->id, 'can_view' => true, 'can_submit_claims' => false], $this->lawyer()->id);
        $token = basename(parse_url($res['url'], PHP_URL_PATH));

        $this->get('/invitation/'.$token)->assertOk();
        $this->post('/invitation/'.$token, ['password' => 'Strong-pass-123', 'password_confirmation' => 'Strong-pass-123'])->assertRedirect(route('home'));
        $u = User::where('email', 'new@example.test')->first();
        $this->assertSame('client', $u->role);
        $this->assertTrue($u->canAccessClient($client->id, 'view'));
        $this->assertFalse($u->canAccessClient($client->id, 'claims'));

        $this->post('/logout');
        $this->post('/invitation/'.$token, ['password' => 'Strong-pass-123', 'password_confirmation' => 'Strong-pass-123'])->assertSessionHasErrors('password');

        $res2 = app(InvitationService::class)->create(['email' => 'late@example.test', 'name' => 'متأخر', 'role' => 'client', 'client_id' => $client->id], null);
        Invitation::latest('id')->first()->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->post(parse_url($res2['url'], PHP_URL_PATH), ['password' => 'Strong-pass-123', 'password_confirmation' => 'Strong-pass-123'])->assertSessionHasErrors('password');
        $this->assertNull(User::where('email', 'late@example.test')->first());
    }

    public function test_invitation_survives_mail_failure_and_returns_link(): void
    {
        // خادم بريد بكلمة مرور خاطئة أو غير موجود: يجب أن تُنشأ الدعوة ويُعاد رابطها
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $this->artisan('office:invite-lawyer', ['email' => 'first@lawyer.test', 'name' => 'محامٍ'])
            ->expectsOutputToContain('/invitation/')
            ->assertExitCode(0);
        $this->assertSame(1, Invitation::where('email', 'first@lawyer.test')->whereNull('revoked_at')->count());
    }

    public function test_password_needs_only_four_characters_of_any_kind(): void
    {
        $this->demo();
        $client = $this->client('شركة الأمثلة');
        $res = app(InvitationService::class)->create(['email' => 'short@example.test', 'name' => 'مستخدم', 'role' => 'client', 'client_id' => $client->id, 'can_view' => true, 'can_submit_claims' => false], $this->lawyer()->id);
        $path = parse_url($res['url'], PHP_URL_PATH);
        $this->post($path, ['password' => '123', 'password_confirmation' => '123'])->assertSessionHasErrors('password');
        $this->post($path, ['password' => '1234', 'password_confirmation' => '1234'])->assertRedirect(route('home'));
    }

    public function test_login_with_username_containing_spaces(): void
    {
        $this->demo();
        $u = $this->lawyer();
        $this->actingAs($u)->put('/account/security/username', ['username' => '  فيصل   الحميضان '])->assertSessionHasNoErrors();
        $this->assertSame('فيصل الحميضان', $u->fresh()->username);
        $this->post('/logout');
        // المسافات الزائدة لا تهم، والبحث لا يميّز حالة الأحرف اللاتينية
        $this->post('/login', ['email' => 'فيصل  الحميضان', 'password' => 'Demo-Pass-2026'])->assertRedirect(route('home'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'lawyer@example.test', 'password' => 'Demo-Pass-2026'])->assertRedirect(route('home'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'فيصل الحميضان', 'password' => 'wrong'])->assertSessionHasErrors('email');
        // اسم مستخدم مكرر أو يحوي @ يُرفض
        $other = \App\Models\User::where('email', 'cfo@example.test')->first();
        $this->actingAs($other)->put('/account/security/username', ['username' => 'فيصل الحميضان'])->assertSessionHasErrors('username');
        $this->actingAs($other)->put('/account/security/username', ['username' => 'a@b'])->assertSessionHasErrors('username');
    }

    public function test_office_creates_client_account_directly_without_email(): void
    {
        $this->demo();
        $client = $this->client('شركة الأمثلة');
        $lawyer = $this->lawyer();
        $this->actingAs($lawyer)->post('/office/admin/accounts', ['name' => 'محاسب الشركة', 'username' => 'محاسب  الأمثلة', 'password' => '2468',
            'role' => 'client', 'client_id' => $client->id, 'can_view' => true, 'can_submit_claims' => false])->assertSessionHasNoErrors();
        $u = User::where('username', 'محاسب الأمثلة')->first();
        $this->assertNotNull($u);
        $this->assertNull($u->email);
        $this->assertSame('client', $u->role);
        $this->assertTrue($u->memberships()->where('client_id', $client->id)->where('can_view', true)->exists());
        // تكرار اسم المستخدم مرفوض
        $this->post('/office/admin/accounts', ['name' => 'x', 'username' => 'محاسب الأمثلة', 'password' => '2468', 'role' => 'client', 'client_id' => $client->id, 'can_view' => true])
            ->assertSessionHasErrors('username');
        // المحامي يعيّن كلمة مرور جديدة
        $this->put("/office/admin/users/{$u->id}/password", ['password' => '1357'])->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->post('/login', ['email' => 'محاسب الأمثلة', 'password' => '1357'])->assertRedirect(route('home'));
        // العميل لا يستطيع حذف اسم المستخدم لأن حسابه بلا بريد، ولا يصل لمسارات الإدارة
        $this->put('/account/security/username', ['username' => ''])->assertSessionHasErrors('username');
        $this->put("/office/admin/users/{$lawyer->id}/password", ['password' => '9999'])->assertForbidden();
    }

    public function test_deactivation_ends_existing_sessions_immediately(): void
    {
        $this->demo();
        $this->post('/login', ['email' => 'cfo@example.test', 'password' => DemoSeeder::PASSWORD]);
        $this->get('/portal')->assertOk();
        // يعطّل المحامي الحساب من جلسة أخرى
        $u = $this->user('cfo@example.test');
        app(\App\Services\Auth\AccountManager::class)->deactivate($u);
        $this->get('/portal')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_revocation_logs_out_active_session(): void
    {
        $this->demo();
        $this->post('/login', ['email' => 'salem@example.test', 'password' => DemoSeeder::PASSWORD]);
        $this->get('/portal')->assertOk();
        app(\App\Services\Auth\AccountManager::class)->revokeSessions($this->user('salem@example.test'));
        $this->get('/portal')->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_and_recovery_code(): void
    {
        $this->demo();
        $g = new Google2FA;
        $secret = $g->generateSecretKey(32);
        $lawyer = $this->lawyer();
        $lawyer->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => [\Illuminate\Support\Facades\Hash::make('AAAAA-BBBBB')]])->save();

        $this->post('/login', ['email' => 'lawyer@example.test', 'password' => DemoSeeder::PASSWORD])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/two-factor', ['code' => $g->getCurrentOtp($secret)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($lawyer);

        $this->post('/logout');
        $this->post('/login', ['email' => 'lawyer@example.test', 'password' => DemoSeeder::PASSWORD]);
        $this->post('/two-factor', ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('home'));
        $this->assertCount(0, $lawyer->fresh()->two_factor_recovery_codes);
    }

    public function test_mfa_is_enforced_for_lawyer_when_required(): void
    {
        $this->demo();
        config(['office.mfa_required' => true]);
        $this->actingAs($this->lawyer())->get('/office')->assertRedirect(route('security.show'));
        $this->actingAs($this->lawyer())->get('/account/security')->assertOk();
    }

    public function test_client_cannot_reach_office_routes(): void
    {
        $this->demo();
        $u = $this->user('salem@example.test');
        foreach (['/office', '/office/clients', '/office/cases', '/office/search?q=شركة', '/office/reports/pdf?type=client&id=1&variant=client', '/office/admin/users', '/office/admin/audit'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
        $this->actingAs($this->lawyer())->get('/portal')->assertForbidden();
    }

    public function test_reset_password_link_sets_new_password_and_revokes_sessions(): void
    {
        $this->demo();
        $u = $this->user('salem@example.test');
        $this->actingAs($this->lawyer())->post(route('admin.users.reset-password', $u))->assertSessionHas('invite_url');
        $url = session('invite_url');
        $this->post('/logout');
        $this->post(parse_url($url, PHP_URL_PATH), ['password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1'])->assertRedirect(route('home'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'salem@example.test', 'password' => 'Brand-new-pass-1'])->assertRedirect(route('home'));
    }

    public function test_security_headers_are_present(): void
    {
        $r = $this->get('/login');
        $r->assertOk();
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $r->headers->get('X-Frame-Options'));
    }

    public function test_preview_gate_blocks_before_login(): void
    {
        config(['office.preview_gate' => ['enabled' => true, 'user' => 'preview', 'password_hash' => password_hash('s3cret', PASSWORD_BCRYPT)], 'office.noindex' => true]);
        $this->get('/login')->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('preview:s3cret')])->get('/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
