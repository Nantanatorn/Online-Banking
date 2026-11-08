<?php

namespace App\Services;

use App\Mail\TransactionAlert;
use App\Models\AppNotification;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// สร้างแจ้งเตือนในแอป + ส่งอีเมล เมื่อมีธุรกรรมใหม่ (เรียกจาก event created ของ Transaction)
class TransactionNotifier
{
    public static function notify(Transaction $transaction): void
    {
        $account = $transaction->bankAccount;
        $user = $account?->user;
        if (!$user) {
            return;
        }

        [$title, $message] = self::compose($transaction);

        // แจ้งเตือนในแอป: บันทึกในธุรกรรมเดียวกัน ถ้าโอนล้มเหลวจนย้อนกลับ แจ้งเตือนก็ย้อนตาม
        AppNotification::create([
            'user_id' => $user->userid,
            'type' => $transaction->type,
            'title' => $title,
            'message' => $message,
            'transaction_id' => $transaction->id,
        ]);

        // อีเมล: ส่งหลัง commit สำเร็จเท่านั้น และถ้าส่งไม่ได้ต้องไม่กระทบธุรกรรม
        if ($user->email) {
            DB::afterCommit(function () use ($user, $title, $message) {
                try {
                    Mail::to($user->email)->send(new TransactionAlert($title, $message));
                } catch (\Throwable $e) {
                    Log::warning('Transaction email failed: ' . $e->getMessage());
                }
            });
        }
    }

    private static function compose(Transaction $t): array
    {
        $amount = '฿' . number_format((float) $t->amount, 2);
        $balance = '฿' . number_format((float) $t->balance, 2);
        $acc = $t->account_id;
        $other = $t->to_account_id;

        return match ($t->type) {
            'deposit' => ['Deposit received', "{$amount} was deposited into account {$acc}. Balance: {$balance}."],
            'withdraw' => ['Withdrawal made', "{$amount} was withdrawn from account {$acc}. Balance: {$balance}."],
            'transfer' => ['Money sent', "{$amount} was transferred from account {$acc} to account {$other}. Balance: {$balance}."],
            'receive' => ['Money received', "{$amount} was received in account {$acc} from account {$other}. Balance: {$balance}."],
            default => ['Account activity', "A {$t->type} of {$amount} was made on account {$acc}."],
        };
    }
}
