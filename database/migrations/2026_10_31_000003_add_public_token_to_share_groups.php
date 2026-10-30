<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // รหัสของ "ลิงก์หลัก" (/s/{token}) — หน้าสารบัญรวมทุกรายการที่แชร์ให้ลูกค้า
        Schema::table('share_groups', function (Blueprint $t) {
            $t->string('public_token', 64)->nullable()->unique()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('share_groups', function (Blueprint $t) {
            $t->dropUnique(['public_token']);
            $t->dropColumn('public_token');
        });
    }
};
