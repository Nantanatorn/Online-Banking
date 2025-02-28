<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            // 🔹 รหัสธุรกรรม (UUID เป็น Primary Key)
            $table->uuid('id')->primary();

            // 🔹 บัญชีที่ทำธุรกรรม (เชื่อมกับ `bank_accounts`)
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('account_id')->on('bank_accounts')->onDelete('cascade');

            // 🔹 ประเภทธุรกรรม (ฝาก, ถอน, โอน)
            $table->enum('type', ['deposit', 'withdraw', 'transfer']);

            // 🔹 จำนวนเงินที่เกี่ยวข้องกับธุรกรรม
            $table->decimal('amount', 15, 2);

            // 🔹 บัญชีปลายทาง (กรณีโอนเงิน)
            $table->unsignedBigInteger('to_account_id')->nullable();
            $table->foreign('to_account_id')->references('account_id')->on('bank_accounts')->onDelete('set null');

            // 🔹 เวลาสร้างและอัปเดต
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};

