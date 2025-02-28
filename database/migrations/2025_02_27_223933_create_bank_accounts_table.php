<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            // 🔹 ใช้ตัวเลขสุ่มเป็น Primary Key (7-9 หลัก)
            $table->bigInteger('account_id')->primary()->unsigned();

            // 🔹 เชื่อมกับ `users` (ใช้ userid เป็น Foreign Key)
            $table->string('user_id', 13);
            $table->foreign('user_id')->references('userid')->on('users')->onDelete('cascade');

            // 🔹 จำนวนเงินในบัญชี
            $table->decimal('balance', 15, 2)->default(0);

            // 🔹 เปอร์เซ็นต์ดอกเบี้ย
            $table->decimal('interest', 5, 2)->default(0);

            // 🔹 เวลาสร้างและอัปเดต
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bank_accounts');
    }
};

