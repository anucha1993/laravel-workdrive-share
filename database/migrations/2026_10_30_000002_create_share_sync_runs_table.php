<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ประวัติการซิงก์แต่ละรอบ
        Schema::create('share_sync_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('share_folder_id')->constrained()->cascadeOnDelete();
            $t->string('trigger')->default('manual');   // manual / schedule / import / cli
            $t->string('status')->default('running');   // running / ok / partial / failed
            $t->unsignedInteger('added')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->unsignedInteger('removed')->default(0);
            $t->unsignedInteger('errors')->default(0);
            $t->longText('log')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['share_folder_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_sync_runs');
    }
};
