<?php

namespace Tests\Feature;

use App\Models\ShareGrant;
use App\Models\ShareGroup;
use App\Models\SharePick;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicShareTest extends TestCase
{
    use RefreshDatabase;

    private ShareGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->group = ShareGroup::create(['name' => 'บริษัท A']);
        foreach ([['เอกสารแรงงาน', 'mj1', 'MJ465023'], ['เอกสารแรงงาน', 'mj2', 'MJ465024'], ['สัญญา', 'ct1', 'สัญญา.pdf']] as [$cat, $id, $name]) {
            SharePick::create(['share_group_id' => $this->group->id, 'category' => $cat, 'source' => $name,
                'source_id' => str_pad($id, 37, '0'), 'source_name' => $name, 'source_type' => $id === 'ct1' ? 'file' : 'folder']);
        }
        // แชร์สำเร็จแล้วเฉพาะ MJ465023 และ สัญญา.pdf
        foreach (['mj1', 'ct1'] as $id) {
            ShareGrant::create(['share_group_id' => $this->group->id, 'resource_id' => str_pad($id, 37, '0'),
                'email' => 'a@client.com', 'permission_id' => "p{$id}", 'status' => 'granted']);
        }
    }

    public function test_main_link_lists_only_shared_items_by_category_without_login(): void
    {
        $this->get($this->group->publicUrl())
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSeeInOrder(['สัญญา', 'สัญญา.pdf', 'เอกสารแรงงาน', 'MJ465023'])
            ->assertDontSee('MJ465024')
            ->assertSee('https://workdrive.zoho.com/folder/' . str_pad('mj1', 37, '0'), false);
    }

    public function test_wrong_token_is_404(): void
    {
        $this->get('/s/not-a-real-token')->assertNotFound();
    }

    public function test_regenerate_invalidates_old_link(): void
    {
        $old = $this->group->publicUrl();

        $this->actingAs(User::factory()->create())->post("/workdrive/{$this->group->id}/public-link")->assertRedirect();

        $this->get($old)->assertNotFound();
        $this->get($this->group->fresh()->publicUrl())->assertOk();
    }

    public function test_group_page_with_recipients_but_no_grants_yet(): void
    {
        $fresh = ShareGroup::create(['name' => 'ยังไม่เคยแชร์']);
        SharePick::create(['share_group_id' => $fresh->id, 'category' => 'ก', 'source' => 'MJ1', 'source_id' => str_pad('x', 37, '0')]);
        \App\Models\ShareRecipient::create(['share_group_id' => $fresh->id, 'email' => 'b@client.com']);

        $this->actingAs(User::factory()->create())->get("/workdrive/{$fresh->id}")
            ->assertOk()
            ->assertSee('มีสิทธิ์ที่ยังไม่ได้อัปเดต 1 รายการ');
    }

    public function test_group_page_shows_main_link(): void
    {
        $this->actingAs(User::factory()->create())->get("/workdrive/{$this->group->id}")
            ->assertOk()
            ->assertSee('ลิงก์หลักสำหรับส่งลูกค้า')
            ->assertSee($this->group->fresh()->publicUrl(), false);
    }
}
