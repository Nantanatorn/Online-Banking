<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// created_at/updated_at ของ transactions เดิมละเอียดแค่วินาที ทำให้ประวัติที่ทำในวินาทีเดียวกันเรียงสลับกัน
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('created_at', 6)->nullable()->change();
            $table->timestamp('updated_at', 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('created_at', 0)->nullable()->change();
            $table->timestamp('updated_at', 0)->nullable()->change();
        });
    }
};
