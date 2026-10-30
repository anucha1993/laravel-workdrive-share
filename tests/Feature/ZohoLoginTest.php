<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZohoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workdrive.login.client_id' => 'login-cid', 'workdrive.login.client_secret' => 'login-secret',
                'workdrive.login.allowed_domains' => 'thefirstgoodmangroup.com']);
    }

    private function fakeZoho(string $email): void
    {
        $claims = rtrim(strtr(base64_encode(json_encode(['email' => $email, 'email_verified' => true, 'name' => 'ทดสอบ'])), '+/', '-_'), '=');
        Http::fake(['accounts.zoho.com/oauth/v2/token' => Http::response([
            'access_token' => 'at', 'id_token' => "h.{$claims}.s", 'expires_in' => 3600,
        ])]);
    }

    public function test_redirects_to_zoho_with_openid_scope(): void
    {
        $res = $this->get('/auth/zoho');

        $res->assertRedirect();
        $url = $res->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.zoho.com/oauth/v2/auth?', $url);
        $this->assertStringContainsString('scope=openid+email+profile', $url);
        $this->assertStringContainsString(urlencode(route('auth.zoho.callback')), $url);
    }

    public function test_company_account_can_login(): void
    {
        $this->fakeZoho('Staff@TheFirstGoodManGroup.com');

        $this->withSession(['zoho_oauth_states' => ['st']])
            ->get('/auth/zoho/callback?code=c&state=st')
            ->assertRedirect(route('workdrive.index'));

        $this->assertAuthenticatedAs(User::where('email', 'staff@thefirstgoodmangroup.com')->first());
    }

    public function test_outside_domain_is_rejected(): void
    {
        $this->fakeZoho('someone@gmail.com');

        $this->withSession(['zoho_oauth_states' => ['st']])
            ->get('/auth/zoho/callback?code=c&state=st')
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_wrong_state_is_rejected_with_retry_link(): void
    {
        $this->withSession(['zoho_oauth_states' => ['st']])
            ->get('/auth/zoho/callback?code=c&state=other')
            ->assertForbidden()
            ->assertSee(route('auth.zoho'), false);

        $this->assertGuest();
    }

    public function test_token_error_from_zoho_is_shown(): void
    {
        Http::fake(['accounts.zoho.com/oauth/v2/token' => Http::response(['error' => 'invalid_client'])]);

        $this->withSession(['zoho_oauth_states' => ['st']])
            ->get('/auth/zoho/callback?code=c&state=st')
            ->assertUnauthorized()
            ->assertSee('invalid_client');
    }

    public function test_login_started_in_two_tabs_accepts_the_older_state(): void
    {
        $this->fakeZoho('staff@thefirstgoodmangroup.com');

        $this->withSession(['zoho_oauth_states' => ['tab1', 'tab2']])
            ->get('/auth/zoho/callback?code=c&state=tab1')
            ->assertRedirect(route('workdrive.index'));

        $this->assertAuthenticated();
    }

    public function test_refreshing_callback_after_login_goes_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/auth/zoho/callback?code=used&state=used')
            ->assertRedirect(route('workdrive.index'));
    }

    public function test_inside_iframe_shows_open_in_new_tab(): void
    {
        $this->get('/auth/zoho', ['Sec-Fetch-Dest' => 'iframe'])
            ->assertOk()
            ->assertSee('target="_blank"', false)
            ->assertSee('/auth/zoho', false);
    }

    public function test_guest_is_sent_to_zoho_login(): void
    {
        $this->get('/workdrive')->assertRedirect('/login');
    }

    public function test_logged_in_user_sees_pages(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/workdrive')->assertOk()->assertSee('กลุ่มแชร์ภายนอก');

        $folder = \App\Models\ShareGroup::create(['name' => 'บริษัท A']);
        $folder->runs()->create(['trigger' => 'manual', 'status' => 'partial', 'errors' => 1, 'log' => 'เปิดต้นฉบับไม่ได้ xyz',
                                 'started_at' => now()->subMinute(), 'finished_at' => now()]);

        $this->actingAs($user)->get("/workdrive/{$folder->id}")->assertOk()->assertSee('ผิดพลาดบางส่วน')->assertSee('กดแชร์');
        $this->actingAs($user)->get("/workdrive/{$folder->id}/history")->assertOk()->assertSee('เปิดต้นฉบับไม่ได้ xyz');
    }
}
