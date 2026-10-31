<?php

namespace Tests\Feature;

use App\Models\ShareAccessLog;
use App\Models\ShareGroup;
use App\Models\SharePick;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeWorkDrive;
use Tests\TestCase;
use ZipArchive;

/** ลิงก์ลูกค้า: หน้าเบราว์เซอร์ไฟล์ (โครงสร้างจำลองตามโฟลเดอร์จริงของลูกค้าใน Accounts) */
class PublicShareTest extends TestCase
{
    use RefreshDatabase;

    private FakeWorkDrive $wd;
    private ShareGroup $group;
    private array $id = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $accounts = FakeWorkDrive::id('accounts');
        config(['workdrive.client_id' => 'cid', 'workdrive.client_secret' => 'secret', 'workdrive.refresh_token' => 'rt',
                'workdrive.request_delay_ms' => 0, 'workdrive.company_root_id' => $accounts]);

        // Accounts / คุณพงษ์ศักดิ์ ไพศาลนิยมศรี / {Associated Contacts, Associated Deals, MH468133}
        $w = $this->wd = new FakeWorkDrive();
        $w->addFolder($accounts, 'Accounts');
        $cust = $w->addFolder(FakeWorkDrive::id('cust'), 'คุณพงษ์ศักดิ์ ไพศาลนิยมศรี', $accounts);
        $this->id['contacts'] = $w->addFolder(FakeWorkDrive::id('contacts'), 'Associated Contacts', $cust);
        $this->id['contactFile'] = $w->addFile(FakeWorkDrive::id('contactfile'), 'บัตรประชาชน.pdf', $this->id['contacts']);
        $this->id['deals'] = $w->addFolder(FakeWorkDrive::id('deals'), 'Associated Deals', $cust);
        $this->id['mh'] = $w->addFolder(FakeWorkDrive::id('mh468133'), 'MH468133', $cust);
        $this->id['bt22'] = $w->addFile(FakeWorkDrive::id('bt22'), 'MH468133_BT22_IO.pdf', $this->id['mh']);
        $w->addFile(FakeWorkDrive::id('thumbs'), 'Thumbs.db', $this->id['mh'], 'file');
        $this->id['sub'] = $w->addFolder(FakeWorkDrive::id('sub'), 'เอกสารเพิ่มเติม', $this->id['mh']);
        $this->id['inner'] = $w->addFile(FakeWorkDrive::id('inner'), 'inner.pdf', $this->id['sub']);
        $this->id['writer'] = $w->addFile(FakeWorkDrive::id('writer'), 'บันทึก', $this->id['mh'], 'writer');
        $other = $w->addFolder(FakeWorkDrive::id('othercust'), 'ลูกค้าอื่น', $accounts);
        $this->id['otherFile'] = $w->addFile(FakeWorkDrive::id('secret'), 'secret.pdf', $other);
        $w->fake();

        $this->group = ShareGroup::create(['name' => 'คุณพงษ์ศักดิ์ ไพศาลนิยมศรี', 'company_folder_id' => $cust, 'company_name' => 'คุณพงษ์ศักดิ์ ไพศาลนิยมศรี']);
        SharePick::create(['share_group_id' => $this->group->id, 'category' => '', 'source' => 'MH468133',
            'source_id' => $this->id['mh'], 'source_name' => 'MH468133', 'source_type' => 'folder']);
    }

    protected function tearDown(): void
    {
        $this->assertSame([], $this->wd->writes(), 'ต้องอ่านอย่างเดียว');
        parent::tearDown();
    }

    private function url(string $route = 'share', ...$params): string
    {
        return route("public.{$route}", [$this->group->fresh()->public_token ?? $this->token(), ...$params]);
    }

    private function token(): string
    {
        $this->group->publicUrl();
        return $this->group->fresh()->public_token;
    }

    public function test_root_shows_customer_as_top_folder_and_only_picked_items(): void
    {
        $this->get($this->url())
            ->assertOk()
            ->assertSee('คุณพงษ์ศักดิ์ ไพศาลนิยมศรี')
            ->assertSee('MH468133')
            ->assertSee('Last Modified')
            ->assertDontSee('Associated Contacts')
            ->assertDontSee('Associated Deals')
            ->assertDontSee('fake-token');
    }

    public function test_open_picked_folder_and_subfolder_with_breadcrumb(): void
    {
        $this->get($this->url('folder', $this->id['mh']))
            ->assertOk()
            ->assertSeeInOrder(['คุณพงษ์ศักดิ์ ไพศาลนิยมศรี', 'MH468133', 'เอกสารเพิ่มเติม', 'MH468133_BT22_IO.pdf'])
            ->assertDontSee('Thumbs.db');

        $this->get($this->url('folder', $this->id['sub']))
            ->assertOk()
            ->assertSeeInOrder(['คุณพงษ์ศักดิ์ ไพศาลนิยมศรี', 'MH468133', 'เอกสารเพิ่มเติม', 'inner.pdf']);
    }

    public function test_anything_not_under_picks_is_404_and_logged(): void
    {
        foreach ([
            ['folder', $this->id['contacts']],
            ['download', $this->id['contactFile']],
            ['download', $this->id['otherFile']],
            ['zip', $this->id['deals']],
            ['folder', FakeWorkDrive::id('cust')],
        ] as [$route, $id]) {
            $this->get($this->url($route, $id))->assertNotFound();
        }

        $this->assertSame(5, ShareAccessLog::where('action', 'denied')->count());
    }

    public function test_download_file_through_server(): void
    {
        $res = $this->get($this->url('download', $this->id['inner']));

        $res->assertOk();
        $this->assertSame(FakeWorkDrive::content($this->id['inner']), $res->streamedContent());
        $this->assertStringContainsString('inner.pdf', $res->headers->get('Content-Disposition'));
        $this->assertSame('download', ShareAccessLog::latest('id')->value('action'));
    }

    public function test_zoho_writer_file_is_converted_to_docx(): void
    {
        $res = $this->get($this->url('download', $this->id['writer']));

        $this->assertSame(FakeWorkDrive::content($this->id['writer'], 'docx'), $res->streamedContent());
        $this->assertStringContainsString('.docx', urldecode($res->headers->get('Content-Disposition')));
    }

    public function test_download_all_as_zip(): void
    {
        $res = $this->get($this->url('zip'));
        $res->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($tmp, $res->streamedContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        sort($names);
        $this->assertSame([
            'MH468133/MH468133_BT22_IO.pdf',
            'MH468133/บันทึก.docx',
            'MH468133/เอกสารเพิ่มเติม/inner.pdf',
        ], $names);
        $this->assertSame(FakeWorkDrive::content($this->id['inner']), $zip->getFromName('MH468133/เอกสารเพิ่มเติม/inner.pdf'));
        $zip->close();
        unlink($tmp);
    }

    public function test_password_protected_link(): void
    {
        $this->group->update(['link_password' => 'secret123']);

        $this->get($this->url())->assertOk()->assertSee('ป้องกันด้วยรหัสผ่าน')->assertDontSee('MH468133');
        $this->get($this->url('download', $this->id['inner']))->assertForbidden();

        $this->post($this->url('unlock'), ['password' => 'wrong'])->assertSessionHas('error');
        $this->post($this->url('unlock'), ['password' => 'secret123'])->assertRedirect($this->url());
        $this->get($this->url())->assertSee('MH468133');

        $this->assertSame(['unlock_failed', 'unlock'], ShareAccessLog::whereIn('action', ['unlock', 'unlock_failed'])->orderBy('id')->pluck('action')->all());
    }

    public function test_expired_or_disabled_link_is_unavailable(): void
    {
        $url = $this->url();

        $this->group->update(['link_expires_at' => now()->subDay()]);
        $this->get($url)->assertStatus(410);

        $this->group->update(['link_expires_at' => null, 'link_enabled' => false]);
        $this->get($url)->assertStatus(410);
        $this->get($this->url('download', $this->id['inner']))->assertForbidden();
    }

    public function test_regenerate_revokes_old_link(): void
    {
        $old = $this->url();
        $this->actingAs(User::factory()->create())->post("/workdrive/{$this->group->id}/public-link");

        $this->get($old)->assertNotFound();
        $this->get($this->url())->assertOk();
    }

    public function test_category_shows_as_virtual_folder(): void
    {
        SharePick::where('source_id', $this->id['mh'])->update(['category' => 'เอกสารแรงงาน']);

        $this->get($this->url())->assertSee('เอกสารแรงงาน')->assertDontSee('MH468133');
        $this->get($this->url('category', 'เอกสารแรงงาน'))->assertOk()->assertSee('MH468133');
    }

    public function test_missing_download_scope_shows_error_not_token(): void
    {
        $this->wd->downloadScopeMissing = true;
        config(['app.debug' => false]); // เหมือน production

        $this->get($this->url('download', $this->id['inner']))
            ->assertStatus(502)
            ->assertDontSee('fake-token')
            ->assertDontSee('INVALID_OAUTHSCOPE');
    }

    // ------------------------------------------------------------ ฝั่งพนักงาน
    public function test_staff_ticks_items_from_customer_folder(): void
    {
        SharePick::query()->delete();
        $user = User::factory()->create();

        // เปิดหน้าปกติ: ไม่ดึงรายการจาก WorkDrive เลย (เร็ว)
        $before = count($this->wd->requests);
        $this->actingAs($user)->get("/workdrive/{$this->group->id}")
            ->assertOk()->assertSee('เรียกดูโฟลเดอร์ลูกค้า')->assertDontSee('Associated Contacts');
        $this->assertCount($before, $this->wd->requests, 'เปิดหน้าต้องไม่เรียก Zoho');

        // กดเรียกดู: ค่อยดึงรายการ
        $this->actingAs($user)->get("/workdrive/{$this->group->id}?browse={$this->group->company_folder_id}")
            ->assertOk()->assertSee('Associated Contacts')->assertSee('MH468133');

        $this->actingAs($user)->post("/workdrive/{$this->group->id}/picks/select", [
            'browse' => $this->group->company_folder_id,
            'ids' => [$this->id['mh'], $this->id['otherFile']], // id นอกโฟลเดอร์ที่เปิดอยู่ต้องถูกข้าม
        ]);

        $this->assertSame([$this->id['mh']], $this->group->picks()->pluck('source_id')->all());
    }

    public function test_staff_updates_link_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post("/workdrive/{$this->group->id}/link", ['password' => 'abcd', 'expires_at' => now()->addWeek()->toDateString(), 'enabled' => 1]);
        $g = $this->group->fresh();
        $this->assertTrue(password_verify('abcd', $g->link_password));
        $this->assertTrue($g->link_expires_at->isFuture());

        $this->actingAs($user)->post("/workdrive/{$this->group->id}/link", ['clear_password' => 1, 'enabled' => 0]);
        $g = $this->group->fresh();
        $this->assertNull($g->link_password);
        $this->assertFalse($g->link_enabled);
    }
}
