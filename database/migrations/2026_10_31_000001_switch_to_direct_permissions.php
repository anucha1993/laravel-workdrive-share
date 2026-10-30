<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เปลี่ยนจาก "copy + sync ไปโฟลเดอร์แชร์" เป็น "แชร์สิทธิ์บนต้นฉบับโดยตรง"
 * ตารางเดิมยังไม่มีข้อมูล (ตรวจแล้ว 30/10/2026) จึงลบทิ้งได้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('share_sync_runs');
        Schema::dropIfExists('share_copies');
        Schema::dropIfExists('share_picks');
        Schema::dropIfExists('share_folders');

        // กลุ่มแชร์ (เช่น 1 บริษัท = 1 กลุ่ม)
        Schema::create('share_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamp('last_applied_at')->nullable();
            $t->text('last_result')->nullable();
            $t->timestamps();
        });

        // ไฟล์/โฟลเดอร์ต้นฉบับที่ pick ไว้
        Schema::create('share_picks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_group_id')->constrained()->cascadeOnDelete();
            $t->string('category');
            $t->text('source');                     // URL หรือ ID ที่ผู้ใช้ใส่
            $t->string('source_id');
            $t->string('source_name')->nullable();
            $t->string('source_type')->nullable();  // file / folder
            $t->text('permalink')->nullable();
            $t->string('status')->default('pending'); // pending / ok / error
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['share_group_id', 'category', 'source_id']);
        });

        // อีเมลผู้รับภายนอก
        Schema::create('share_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_group_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('name')->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->unique(['share_group_id', 'email']);
        });

        // สิทธิ์ที่ระบบให้ไว้จริงใน WorkDrive (1 แถว = ไฟล์/โฟลเดอร์ 1 รายการ × อีเมล 1 คน)
        // ไม่ cascade จาก pick/recipient เพราะต้องเก็บ permission_id ไว้ถอนสิทธิ์หลังเอาออก
        Schema::create('share_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_group_id')->constrained()->restrictOnDelete();
            $t->string('resource_id');
            $t->string('email');
            $t->string('permission_id')->nullable();
            $t->string('status');                   // granted / error
            $t->text('error')->nullable();
            $t->timestamp('granted_at')->nullable();
            $t->timestamps();
            $t->unique(['share_group_id', 'resource_id', 'email']);
            $t->index(['resource_id', 'email']);
        });

        // ประวัติการแชร์/ถอนสิทธิ์แต่ละรอบ
        Schema::create('share_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_group_id')->constrained()->cascadeOnDelete();
            $t->string('trigger')->default('manual');
            $t->string('status')->default('running'); // running / ok / partial / failed
            $t->unsignedInteger('granted')->default(0);
            $t->unsignedInteger('revoked')->default(0);
            $t->unsignedInteger('errors')->default(0);
            $t->longText('log')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['share_group_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_runs');
        Schema::dropIfExists('share_grants');
        Schema::dropIfExists('share_recipients');
        Schema::dropIfExists('share_picks');
        Schema::dropIfExists('share_groups');
    }
};
