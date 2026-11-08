<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// โค้ดโอนเงินบันทึกธุรกรรมฝั่งผู้รับเป็น type = 'receive' แต่ enum เดิมไม่มีค่านี้
return new class extends Migration {
    public function up(): void
    {
        $this->changeType(['deposit', 'withdraw', 'transfer', 'receive']);
    }

    public function down(): void
    {
        // ย้อนกลับไม่ได้ถ้ายังมีแถว receive อยู่ จึงแปลงเป็น transfer แทนการลบข้อมูล
        DB::table('transactions')->where('type', 'receive')->update(['type' => 'transfer']);
        $this->changeType(['deposit', 'withdraw', 'transfer']);
    }

    private function changeType(array $values): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Postgres: enum ของ Laravel เป็น CHECK constraint
            $list = implode(', ', array_map(fn ($v) => "'{$v}'", $values));
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text IN ({$list}))");
            return;
        }

        // SQLite / MySQL: ให้ schema builder จัดการ (SQLite จะสร้างตารางใหม่ให้)
        Schema::table('transactions', function (Blueprint $table) use ($values) {
            $table->enum('type', $values)->change();
        });
    }
};
