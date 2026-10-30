<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // โฟลเดอร์แชร์ (เช่น 1 บริษัท = 1 โฟลเดอร์)
        Schema::create('share_folders', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('wd_folder_id')->nullable();
            $t->string('share_link')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->text('last_sync_result')->nullable();
            $t->timestamps();
        });

        // รายการที่ pick ไว้ (ไฟล์หรือโฟลเดอร์ต้นฉบับ)
        Schema::create('share_picks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_folder_id')->constrained()->cascadeOnDelete();
            $t->string('category');
            $t->text('source');                 // URL หรือ ID ที่ผู้ใช้ใส่
            $t->string('source_id');
            $t->string('source_name')->nullable();
            $t->string('source_type')->nullable(); // file / folder
            $t->string('status')->default('pending'); // pending / ok / error
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['share_folder_id', 'category', 'source_id']);
        });

        // สถานะสำเนาที่ซิงก์แล้ว
        Schema::create('share_copies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_folder_id')->constrained()->cascadeOnDelete();
            $t->string('source_file_id');
            $t->string('path');                 // หมวด/โฟลเดอร์ย่อย/ชื่อไฟล์
            $t->string('name');
            $t->string('dest_file_id');
            $t->string('dest_parent_id');
            $t->string('source_mtime')->nullable();
            $t->timestamps();
            $t->unique(['share_folder_id', 'source_file_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_copies');
        Schema::dropIfExists('share_picks');
        Schema::dropIfExists('share_folders');
    }
};
