<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetTransferLimit extends Command
{
    protected $signature = 'limit:reset {--user= : userid (เลขบัตร) ที่ต้องการรีเซต ถ้าไม่ระบุจะรีเซตทุกคน}';

    protected $description = 'รีเซตวงเงินโอนรายวัน (ไม่ลบประวัติธุรกรรม ยอดโอนก่อนหน้านี้จะไม่ถูกนับในวงเงินวันนี้)';

    public function handle(): int
    {
        $query = User::query();

        if ($userId = $this->option('user')) {
            $query->where('userid', $userId);
        }

        $count = $query->update(['transfer_limit_reset_at' => now()]);

        if ($count === 0) {
            $this->warn('ไม่พบผู้ใช้ที่ต้องการรีเซต');
            return self::FAILURE;
        }

        $this->info("รีเซตวงเงินโอนรายวันแล้ว {$count} บัญชีผู้ใช้");
        return self::SUCCESS;
    }
}
