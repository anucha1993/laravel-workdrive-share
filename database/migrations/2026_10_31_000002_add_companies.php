<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // รายชื่อโฟลเดอร์บริษัทใต้ Accounts (เก็บไว้เพราะอ่านจาก Zoho ครั้งละหลายนาที)
        Schema::create('wd_companies', function (Blueprint $t) {
            $t->id();
            $t->string('folder_id')->unique();
            $t->string('name');
            $t->timestamps();
            $t->index('name');
        });

        // กลุ่มแชร์ผูกกับโฟลเดอร์บริษัท เพื่อให้วางแค่ชื่อโฟลเดอร์ย่อยได้
        Schema::table('share_groups', function (Blueprint $t) {
            $t->string('company_folder_id')->nullable()->after('name');
            $t->string('company_name')->nullable()->after('company_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('share_groups', function (Blueprint $t) {
            $t->dropColumn(['company_folder_id', 'company_name']);
        });
        Schema::dropIfExists('wd_companies');
    }
};
