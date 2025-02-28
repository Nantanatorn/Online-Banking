<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('userid', 13)->primary(); // ใช้ idcard เป็น userid
            $table->string('firstname');
            $table->string('lastname');
            $table->string('idcard', 13)->unique(); // ต้องเป็นค่าเดียวกับ `userid`
            $table->string('phone')->unique();
            $table->string('email')->unique();
            $table->text('address');
            $table->string('gender'); // ใช้ String แทน ENUM
            $table->string('pin'); // Hash ค่า PIN
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};


