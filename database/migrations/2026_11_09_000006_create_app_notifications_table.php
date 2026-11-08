<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// แจ้งเตือนในแอปเมื่อเงินเข้า/ออกบัญชี (ตาราง notifications ของ Laravel ใช้ id แบบตัวเลข ไม่เข้ากับ userid ที่เป็น string)
return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 13);
            $table->foreign('user_id')->references('userid')->on('users')->onDelete('cascade');
            $table->string('type', 20);
            $table->string('title');
            $table->text('message');
            $table->string('transaction_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
