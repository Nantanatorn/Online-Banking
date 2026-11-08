<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// บัญชีปลายทางที่ผู้ใช้บันทึก (bookmark) ไว้สำหรับโอนเงิน
return new class extends Migration {
    public function up(): void
    {
        Schema::create('saved_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 13);
            $table->foreign('user_id')->references('userid')->on('users')->onDelete('cascade');
            $table->bigInteger('account_id')->unsigned();
            $table->foreign('account_id')->references('account_id')->on('bank_accounts')->onDelete('cascade');
            $table->string('nickname', 50)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_accounts');
    }
};
