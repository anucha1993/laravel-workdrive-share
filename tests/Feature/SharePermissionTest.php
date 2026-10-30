<?php

namespace Tests\Feature;

use App\Mail\ShareSummaryMail;
use App\Models\ShareGroup;
use App\Models\SharePick;
use App\Models\ShareRecipient;
use App\Models\User;
use App\Services\WorkDrive\SharePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeWorkDrive;
use Tests\TestCase;

class SharePermissionTest extends TestCase
{
    use RefreshDatabase;

    private FakeWorkDrive $wd;
    private ShareGroup $group;
    private string $contract;
    private string $budgetDir;

    protected function setUp(): void
    {
        parent::setUp();

        config(['workdrive.client_id' => 'cid', 'workdrive.client_secret' => 'secret', 'workdrive.refresh_token' => 'rt',
                'workdrive.request_delay_ms' => 0]);
        Mail::fake();

        $this->wd = new FakeWorkDrive();
        $this->contract = $this->wd->addFile(FakeWorkDrive::id('srccontract'), 'สัญญา1.pdf');
        $this->budgetDir = $this->wd->addFolder(FakeWorkDrive::id('srcbudget'), 'งบ 2026');
        $this->wd->fake();

        $this->group = ShareGroup::create(['name' => 'บริษัท A']);
        $this->pick('สัญญา', $this->contract);
        $this->pick('บัญชี', $this->budgetDir);
        $this->recipient('a@client.com');
        $this->recipient('b@client.com');
    }

    protected function tearDown(): void
    {
        // ข้อกำหนดหลัก: เรียกได้แค่ อ่านไฟล์ และ เพิ่ม/ถอนสิทธิ์ — ไม่มีคำสั่งเขียนใดๆ ไปที่ /files
        foreach ($this->wd->writes() as $w) {
            $this->assertMatchesRegularExpression('#^/permissions(/\w+)?$#', $w['path'], "มีคำสั่ง {$w['method']} {$w['path']} ที่ไม่ใช่การแชร์");
        }

        parent::tearDown();
    }

    private function pick(string $category, string $sourceId): SharePick
    {
        return SharePick::create(['share_group_id' => $this->group->id, 'category' => $category, 'source' => $sourceId, 'source_id' => $sourceId]);
    }

    private function recipient(string $email): ShareRecipient
    {
        return ShareRecipient::create(['share_group_id' => $this->group->id, 'email' => $email]);
    }

    private function apply(?ShareGroup $group = null): array
    {
        return app(SharePermissionService::class)->apply(($group ?? $this->group)->fresh());
    }

    public function test_share_grants_view_on_every_pick_to_every_recipient(): void
    {
        $r = $this->apply();

        $this->assertSame(4, $r['granted']);
        $this->assertSame(0, $r['errors']);
        $this->assertSame([
            "{$this->budgetDir}|a@client.com", "{$this->budgetDir}|b@client.com",
            "{$this->contract}|a@client.com", "{$this->contract}|b@client.com",
        ], $this->wd->shared());

        // role ดูอย่างเดียว: ไฟล์ 34, โฟลเดอร์ 6 และไม่ให้ Zoho ส่งอีเมลซ้ำซ้อน
        foreach ($this->wd->permissions as $p) {
            $this->assertSame($p['resource_id'] === $this->contract ? '34' : '6', $p['role_id']);
            $this->assertFalse($p['notify']);
        }

        // เก็บ permission_id ไว้ถอนสิทธิ์ภายหลัง
        $this->assertEqualsCanonicalizing(array_keys($this->wd->permissions), $this->group->grants()->pluck('permission_id')->all());

        $pick = SharePick::where('source_id', $this->budgetDir)->first();
        $this->assertSame(['งบ 2026', 'folder', 'ok'], [$pick->source_name, $pick->source_type, $pick->status]);
    }

    public function test_reapply_without_changes_makes_no_write_calls(): void
    {
        $this->apply();
        $before = count($this->wd->writes());

        $r = $this->apply();

        $this->assertSame([0, 0, 0], [$r['granted'], $r['revoked'], $r['errors']]);
        $this->assertCount($before, $this->wd->writes());
    }

    public function test_removing_pick_revokes_only_that_resource(): void
    {
        $this->apply();

        SharePick::where('source_id', $this->budgetDir)->delete();
        $r = $this->apply();

        $this->assertSame(2, $r['revoked']);
        $this->assertSame(["{$this->contract}|a@client.com", "{$this->contract}|b@client.com"], $this->wd->shared());
        $this->assertSame(2, $this->group->grants()->count());
    }

    public function test_removing_recipient_revokes_all_their_permissions(): void
    {
        $this->apply();

        ShareRecipient::where('email', 'a@client.com')->delete();
        $r = $this->apply();

        $this->assertSame(2, $r['revoked']);
        $this->assertSame(["{$this->budgetDir}|b@client.com", "{$this->contract}|b@client.com"], $this->wd->shared());
    }

    public function test_unreadable_source_is_not_shared_and_existing_permissions_are_kept(): void
    {
        $this->apply();

        // ต้นฉบับเปิดไม่ได้ชั่วคราว + มีผู้รับใหม่ในรอบเดียวกัน
        $this->wd->items[$this->budgetDir]['status'] = 51;
        $this->recipient('c@client.com');
        $r = $this->apply();

        $this->assertSame(1, $r['errors']);
        $this->assertSame(0, $r['revoked'], 'ต้องไม่ถอนสิทธิ์เดิมเพราะเปิดต้นฉบับไม่ได้');
        $this->assertSame(1, $r['granted']); // c ได้เฉพาะสัญญา
        $this->assertContains("{$this->budgetDir}|a@client.com", $this->wd->shared());
        $this->assertNotContains("{$this->budgetDir}|c@client.com", $this->wd->shared());
        $this->assertSame('error', SharePick::where('source_id', $this->budgetDir)->value('status'));
    }

    public function test_blocked_external_email_is_recorded_as_error_and_retried(): void
    {
        $this->wd->blockedEmails = ['b@client.com'];
        $r = $this->apply();

        $this->assertSame(2, $r['granted']);
        $this->assertSame(2, $r['errors']);
        $err = $this->group->grants()->where('email', 'b@client.com')->first();
        $this->assertSame('error', $err->status);
        $this->assertStringContainsString('R910', $err->error);
        $this->assertStringContainsString('แอดมินไม่อนุญาต', $err->error);

        // แอดมินเปิดสิทธิ์แล้ว กดแชร์อีกครั้งต้องสำเร็จ
        $this->wd->blockedEmails = [];
        $r = $this->apply();
        $this->assertSame(2, $r['granted']);
        $this->assertCount(4, $this->wd->shared());
    }

    public function test_permission_shared_by_another_group_is_not_revoked(): void
    {
        $this->apply();

        $other = ShareGroup::create(['name' => 'บริษัท B']);
        SharePick::create(['share_group_id' => $other->id, 'category' => 'สัญญา', 'source' => $this->contract, 'source_id' => $this->contract]);
        ShareRecipient::create(['share_group_id' => $other->id, 'email' => 'a@client.com']);
        $r = $this->apply($other);
        $this->assertSame(1, $r['granted']);
        $this->assertCount(4, $this->wd->permissions, 'ใช้สิทธิ์เดิมร่วมกัน ไม่ POST ซ้ำ');

        SharePick::where('share_group_id', $this->group->id)->where('source_id', $this->contract)->delete();
        $this->apply();

        $this->assertContains("{$this->contract}|a@client.com", $this->wd->shared(), 'กลุ่ม B ยังใช้สิทธิ์นี้อยู่');
        $this->assertNotContains("{$this->contract}|b@client.com", $this->wd->shared());
    }

    public function test_permission_removed_manually_in_workdrive_is_treated_as_revoked(): void
    {
        $this->apply();
        $this->wd->permissions = []; // มีคนถอนสิทธิ์ใน WorkDrive เองแล้ว

        ShareRecipient::where('email', 'a@client.com')->delete();
        $r = $this->apply();

        $this->assertSame([2, 0], [$r['revoked'], $r['errors']]);
    }

    public function test_apply_button_sends_summary_grouped_by_category_to_new_recipients(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/workdrive/{$this->group->id}/apply", ['notify' => 1])->assertRedirect();

        Mail::assertSent(ShareSummaryMail::class, 2);
        Mail::assertSent(ShareSummaryMail::class, function (ShareSummaryMail $mail) {
            $html = $mail->render();
            return $mail->hasTo('a@client.com')
                && $mail->items->keys()->sort()->values()->all() === ['บัญชี', 'สัญญา']
                && str_contains($html, 'สัญญา1.pdf')
                && str_contains($html, "https://workdrive.zoho.com/folder/{$this->budgetDir}");
        });
        $this->assertNotNull(ShareRecipient::where('email', 'a@client.com')->value('notified_at'));

        // กดซ้ำโดยไม่มีอะไรเปลี่ยน → ไม่ส่งอีเมลซ้ำ
        $this->actingAs($user)->post("/workdrive/{$this->group->id}/apply", ['notify' => 1]);
        Mail::assertSent(ShareSummaryMail::class, 2);
    }

    public function test_deleting_group_revokes_everything_first(): void
    {
        $this->apply();
        $user = User::factory()->create();

        $this->actingAs($user)->delete("/workdrive/{$this->group->id}")->assertRedirect();

        $this->assertSame([], $this->wd->shared());
        $this->assertDatabaseMissing('share_groups', ['id' => $this->group->id]);
    }

    public function test_recipients_form_accepts_multiple_emails(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post("/workdrive/{$this->group->id}/recipients", ['emails' => "X@Other.com, y@other.com\na@client.com"])
            ->assertSessionHas('ok');

        $this->assertSame(['a@client.com', 'b@client.com', 'x@other.com', 'y@other.com'], $this->group->recipients()->pluck('email')->all());
    }

    public function test_apply_command(): void
    {
        $this->artisan('workdrive:apply', ['group' => 'บริษัท A'])
            ->expectsOutputToContain('ให้สิทธิ์ 4')
            ->assertSuccessful();
    }
}
