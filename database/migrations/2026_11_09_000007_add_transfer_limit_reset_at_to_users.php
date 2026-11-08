<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// เวลาที่แอดมินรีเซตวงเงินโอนรายวันล่าสุด: ยอดโอนก่อนเวลานี้ไม่นับรวมในวงเงินวันนี้ (ไม่ลบประวัติธุรกรรม)
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('transfer_limit_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('transfer_limit_reset_at');
        });
    }
};
