<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// เติมยอดคงเหลือ ณ ตอนนั้นให้ธุรกรรมเก่าที่ balance เป็น NULL
// โดยไล่ย้อนจากยอดปัจจุบันของบัญชี (ธุรกรรมใหม่ล่าสุด -> เก่าสุด)
return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('bank_accounts')->get(['account_id', 'balance']) as $account) {
            $running = (string) $account->balance;

            $rows = DB::table('transactions')
                ->where('account_id', $account->account_id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['id', 'type', 'amount', 'balance']);

            foreach ($rows as $row) {
                if ($row->balance === null) {
                    DB::table('transactions')->where('id', $row->id)->update(['balance' => $running]);
                    $after = $running;
                } else {
                    $after = (string) $row->balance;
                }

                // ยอดก่อนหน้าธุรกรรมนี้ = ยอดหลังธุรกรรม -/+ จำนวนเงิน
                $running = in_array($row->type, ['deposit', 'receive'], true)
                    ? bcsub($after, (string) $row->amount, 2)
                    : bcadd($after, (string) $row->amount, 2);
            }
        }
    }

    public function down(): void
    {
        // ไม่ย้อนกลับ: ไม่รู้ว่าแถวไหนเคยเป็น NULL
    }
};
