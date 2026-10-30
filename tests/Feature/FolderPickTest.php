<?php

namespace Tests\Feature;

use App\Models\ShareGroup;
use App\Models\User;
use App\Models\WdCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeWorkDrive;
use Tests\TestCase;

class FolderPickTest extends TestCase
{
    use RefreshDatabase;

    private FakeWorkDrive $wd;
    private User $user;
    private string $tfg;
    private string $mj1;
    private string $mj2;
    private string $contract;
    private string $other;
    private string $otherMj;

    protected function setUp(): void
    {
        parent::setUp();

        $accounts = FakeWorkDrive::id('accounts');
        config(['workdrive.client_id' => 'cid', 'workdrive.client_secret' => 'secret', 'workdrive.refresh_token' => 'rt',
                'workdrive.request_delay_ms' => 0, 'workdrive.company_root_id' => $accounts]);

        // Zoho CRM - Workdrive / Accounts / <บริษัท> / MJxxxxx
        $this->wd = new FakeWorkDrive();
        $this->wd->addFolder($accounts, 'Accounts');
        $this->tfg = $this->wd->addFolder(FakeWorkDrive::id('tfg'), 'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด', $accounts);
        $this->mj1 = $this->wd->addFolder(FakeWorkDrive::id('mj465023'), 'MJ465023', $this->tfg);
        $this->mj2 = $this->wd->addFolder(FakeWorkDrive::id('mj465024'), 'MJ465024', $this->tfg);
        $this->contract = $this->wd->addFile(FakeWorkDrive::id('contract'), 'สัญญาจ้าง.pdf', $this->mj1);
        $this->other = $this->wd->addFolder(FakeWorkDrive::id('royal'), 'บริษัท รอยัล ปอร์ซเลน จำกัด (มหาชน)', $accounts);
        $this->otherMj = $this->wd->addFolder(FakeWorkDrive::id('mj470001'), 'MJ470001', $this->other);
        // ให้มีบริษัทมากกว่า 1 หน้า (50 รายการ) เพื่อทดสอบการแบ่งหน้า
        for ($i = 0; $i < 60; $i++) {
            $this->wd->addFolder(FakeWorkDrive::id("co{$i}x"), "บริษัททดสอบ {$i}", $accounts);
        }
        $this->wd->fake();

        $this->user = User::factory()->create();
        $this->artisan('workdrive:companies')->assertSuccessful();
    }

    private function group(): ShareGroup
    {
        $this->actingAs($this->user)->post('/workdrive', ['company' => 'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด']);

        return ShareGroup::firstOrFail();
    }

    public function test_company_list_is_refreshed_from_accounts(): void
    {
        $this->assertSame(62, WdCompany::count());
        $this->assertSame($this->tfg, WdCompany::where('name', 'like', 'บริษัทนำคนต่างด้าว%')->value('folder_id'));
        $this->assertSame([], $this->wd->writes(), 'อ่านรายชื่ออย่างเดียว');
    }

    public function test_create_group_from_company(): void
    {
        $group = $this->group();

        $this->assertSame($this->tfg, $group->company_folder_id);
        $this->assertSame($group->company_name, $group->name);
    }

    public function test_new_company_can_be_added_instantly_by_link(): void
    {
        // บริษัทใหม่ที่เพิ่งสร้างใน WorkDrive หลังรีเฟรชรายชื่อครั้งล่าสุด
        $new = $this->wd->addFolder(FakeWorkDrive::id('newco'), 'บริษัท ใหม่ล่าสุด จำกัด', config('workdrive.company_root_id'));

        $this->actingAs($this->user)->post('/workdrive', ['company' => "https://workdrive.zoho.com/home/x/folders/{$new}"])
            ->assertRedirect();

        $group = ShareGroup::firstOrFail();
        $this->assertSame([$new, 'บริษัท ใหม่ล่าสุด จำกัด'], [$group->company_folder_id, $group->name]);
        $this->assertTrue(WdCompany::where('folder_id', $new)->exists(), 'เพิ่มเข้ารายชื่อด้วย');
    }

    public function test_link_to_non_company_folder_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/workdrive', ['company' => $this->mj1])->assertSessionHas('error');
        $this->assertSame(0, ShareGroup::count());
    }

    public function test_unknown_company_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/workdrive', ['company' => 'บริษัทไม่มีจริง'])->assertSessionHas('error');
        $this->assertSame(0, ShareGroup::count());
    }

    private const TFG = 'บริษัทนำคนต่างด้าวมาทำงานในประเทศ เดอะ เฟิร์ส กูด แมน กรุ๊ป จำกัด';

    public function test_choose_company_and_paste_folder_names(): void
    {
        $group = $this->group();

        $this->actingAs($this->user)->post("/workdrive/{$group->id}/picks", [
            'company' => self::TFG,
            'category' => 'เอกสารแรงงาน',
            'sources' => "MJ465023\r\n mj465024 \n\nMJ999999",
        ])->assertSessionHas('invalid', fn ($inv) => count($inv) === 1 && str_contains($inv[0], 'MJ999999'));

        $this->assertEqualsCanonicalizing([$this->mj1, $this->mj2], $group->picks()->pluck('source_id')->all());
        $this->assertSame('MJ465023', $group->picks()->where('source_id', $this->mj1)->value('source_name'));
        $this->assertSame([], $this->wd->writes());
    }

    public function test_choose_other_company_in_same_group(): void
    {
        $group = $this->group();

        $this->actingAs($this->user)->post("/workdrive/{$group->id}/picks", [
            'company' => 'บริษัท รอยัล ปอร์ซเลน จำกัด (มหาชน)',
            'category' => 'ก',
            'sources' => "MJ470001\nMJ465023",
        ])->assertSessionHas('invalid', fn ($inv) => count($inv) === 1 && str_contains($inv[0], 'MJ465023'));

        $this->assertSame([$this->otherMj], $group->picks()->pluck('source_id')->all());
        $this->assertSame($this->tfg, $group->fresh()->company_folder_id, 'บริษัทหลักของกลุ่มไม่เปลี่ยน');
    }

    public function test_group_without_company_gets_the_first_chosen_company(): void
    {
        $group = ShareGroup::create(['name' => 'ไม่ผูกบริษัท']);

        $this->actingAs($this->user)->post("/workdrive/{$group->id}/picks", [
            'company' => self::TFG, 'category' => 'ก', 'sources' => 'MJ465023',
        ]);

        $this->assertSame([$this->mj1], $group->picks()->pluck('source_id')->all());
        $this->assertSame($this->tfg, $group->fresh()->company_folder_id);
    }

    public function test_company_must_be_chosen_from_list(): void
    {
        $group = $this->group();

        $this->actingAs($this->user)->post("/workdrive/{$group->id}/picks", [
            'company' => 'พิมพ์ไม่ครบ', 'category' => 'ก', 'sources' => 'MJ465023',
        ])->assertSessionHas('error');

        $this->assertSame(0, $group->picks()->count());
    }
}
