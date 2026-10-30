<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ตั้งค่าความปลอดภัยของลิงก์ลูกค้า (/s/{token})
        Schema::table('share_groups', function (Blueprint $t) {
            $t->string('link_password')->nullable()->after('public_token');   // bcrypt (null = ไม่ต้องใช้รหัส)
            $t->timestamp('link_expires_at')->nullable()->after('link_password');
            $t->boolean('link_enabled')->default(true)->after('link_expires_at');
        });

        // บันทึกการเข้าดู/ดาวน์โหลดของลูกค้า
        Schema::create('share_access_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_group_id')->constrained()->cascadeOnDelete();
            $t->string('action');                 // view / open / download / zip / unlock / unlock_failed / denied
            $t->string('resource_id')->nullable();
            $t->string('name')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['share_group_id', 'id']);
        });

        // หมวดไม่บังคับแล้ว (ว่าง = แสดงที่ระดับบนสุดของลิงก์ลูกค้า)
        Schema::table('share_picks', function (Blueprint $t) {
            $t->string('category')->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_access_logs');
        Schema::table('share_groups', function (Blueprint $t) {
            $t->dropColumn(['link_password', 'link_expires_at', 'link_enabled']);
        });
    }
};
