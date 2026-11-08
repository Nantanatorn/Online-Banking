<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    // วงเงินโอนรวมต่อวัน 500,000 บาท (นับตั้งแต่เที่ยงคืนตามเวลา app timezone = Asia/Bangkok แล้วรีเซตทุกเที่ยงคืน)
    public const DAILY_TRANSFER_LIMIT = '500000.00';
    private const AMOUNT_RULE = 'required|numeric|decimal:0,2|min:1|max:1000000000';

    private const PIN_RULE = ['required', 'string', 'regex:/^\d{4,6}$/'];

    // ตรวจ PIN ก่อนถอน/โอน (จำกัด 5 ครั้ง/นาที กัน brute-force) คืน response เมื่อไม่ผ่าน
    private function verifyPin($user, string $pin)
    {
        $key = 'txn-pin:' . $user->userid;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['error' => 'Too many incorrect PIN attempts. Please wait a minute.'], 429);
        }

        if (!Hash::check($pin, $user->pin)) {
            RateLimiter::hit($key, 60);
            return response()->json(['error' => 'Incorrect PIN'], 403);
        }

        RateLimiter::clear($key);
        return null;
    }

    public function deposit(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate(['amount' => self::AMOUNT_RULE]);

        try {
            return DB::transaction(function () use ($user, $validated) {
                // lock แถวบัญชีก่อนอ่านยอด กัน race condition
                $account = $user->bankAccounts()->orderBy('account_id')->lockForUpdate()->first();
                if (!$account) {
                    return response()->json(['error' => 'No bank account found'], 404);
                }

                $account->balance = bcadd((string) $account->balance, (string) $validated['amount'], 2);
                $account->save();

                $txn = Transaction::create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $account->account_id,
                    'type' => 'deposit',
                    'amount' => $validated['amount'],
                    'balance' => $account->balance,
                    'to_account_id' => null,
                ]);

                return response()->json([
                    'message' => 'Deposit successful',
                    'transaction_id' => $txn->id,
                    'balance' => $account->balance,
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('Deposit failed: ' . $e->getMessage());
            return response()->json(['error' => 'Deposit failed. Please try again.'], 500);
        }
    }

    public function withdraw(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'amount' => self::AMOUNT_RULE,
            'pin' => self::PIN_RULE,
        ]);

        if ($error = $this->verifyPin($user, $validated['pin'])) {
            return $error;
        }

        try {
            return DB::transaction(function () use ($user, $validated) {
                $account = $user->bankAccounts()->orderBy('account_id')->lockForUpdate()->first();
                if (!$account) {
                    return response()->json(['error' => 'No bank account found'], 404);
                }

                // เช็กยอดหลัง lock แล้วเท่านั้น
                if (bccomp((string) $account->balance, (string) $validated['amount'], 2) < 0) {
                    return response()->json(['error' => 'Insufficient balance'], 400);
                }

                $account->balance = bcsub((string) $account->balance, (string) $validated['amount'], 2);
                $account->save();

                $txn = Transaction::create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $account->account_id,
                    'type' => 'withdraw',
                    'amount' => $validated['amount'],
                    'balance' => $account->balance,
                    'to_account_id' => null,
                ]);

                return response()->json([
                    'message' => 'Withdraw successful',
                    'transaction_id' => $txn->id,
                    'balance' => $account->balance,
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('Withdraw failed: ' . $e->getMessage());
            return response()->json(['error' => 'Withdraw failed. Please try again.'], 500);
        }
    }

    public function transfer(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'amount' => self::AMOUNT_RULE,
            'source_account_id' => 'required|integer|exists:bank_accounts,account_id',
            'target_account_id' => 'required|integer|exists:bank_accounts,account_id',
            'pin' => self::PIN_RULE,
        ]);

        if ($error = $this->verifyPin($user, $validated['pin'])) {
            return $error;
        }

        // ห้ามโอนเข้าบัญชีตัวเอง
        if ($validated['source_account_id'] == $validated['target_account_id']) {
            return response()->json(['error' => 'Cannot transfer to the same account'], 400);
        }

        try {
            return DB::transaction(function () use ($user, $validated) {
                $sourceId = (int) $validated['source_account_id'];
                $targetId = (int) $validated['target_account_id'];

                // lock ตามลำดับ account_id เสมอ กัน deadlock เมื่อโอนสวนทางกัน
                $locked = BankAccount::whereIn('account_id', [$sourceId, $targetId])
                    ->orderBy('account_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('account_id');

                $fromAccount = $locked->get($sourceId);
                $toAccount = $locked->get($targetId);

                // ป้องกันโอนจากบัญชีที่ไม่ได้เป็นเจ้าของ
                if (!$fromAccount || (string) $fromAccount->user_id !== (string) $user->userid) {
                    return response()->json(['error' => 'Unauthorized access to source account'], 403);
                }

                if (!$toAccount) {
                    return response()->json(['error' => 'Target account not found'], 404);
                }

                // เช็กวงเงินรายวันหลัง lock บัญชีต้นทางแล้ว กันโอนพร้อมกันหลายรายการทะลุวงเงิน
                $used = $this->transferredToday($user);
                $remaining = bcsub(self::DAILY_TRANSFER_LIMIT, $used, 2);
                if (bccomp((string) $validated['amount'], $remaining, 2) > 0) {
                    return response()->json([
                        'error' => 'Daily transfer limit exceeded. Remaining today: ฿' . number_format((float) $remaining, 2),
                        'remaining' => $remaining,
                    ], 400);
                }

                if (bccomp((string) $fromAccount->balance, (string) $validated['amount'], 2) < 0) {
                    return response()->json(['error' => 'Insufficient balance'], 400);
                }

                $fromAccount->balance = bcsub((string) $fromAccount->balance, (string) $validated['amount'], 2);
                $fromAccount->save();

                $toAccount->balance = bcadd((string) $toAccount->balance, (string) $validated['amount'], 2);
                $toAccount->save();

                // ธุรกรรมฝั่งผู้โอน
                $txn = Transaction::create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $fromAccount->account_id,
                    'type' => 'transfer',
                    'amount' => $validated['amount'],
                    'balance' => $fromAccount->balance,
                    'to_account_id' => $toAccount->account_id,
                ]);

                // ธุรกรรมฝั่งผู้รับ
                Transaction::create([
                    'id' => (string) Str::uuid(),
                    'account_id' => $toAccount->account_id,
                    'type' => 'receive',
                    'amount' => $validated['amount'],
                    'balance' => $toAccount->balance,
                    'to_account_id' => $fromAccount->account_id,
                ]);

                return response()->json([
                    'message' => 'Transfer successful',
                    'transaction_id' => $txn->id,
                    'from_balance' => $fromAccount->balance,
                    'to_balance' => $toAccount->balance,
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('Transfer failed: ' . $e->getMessage());
            return response()->json(['error' => 'Transfer failed. Please try again.'], 500);
        }
    }

    // ค้นหาเจ้าของบัญชีปลายทางให้ผู้โอนเช็กชื่อก่อนยืนยัน (ชื่อจริงเต็ม + นามสกุลย่อเหลืออักษรแรก)
    public function lookupAccount($accountId)
    {
        if (!ctype_digit((string) $accountId)) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $account = BankAccount::with('user')->find((int) $accountId);
        if (!$account || !$account->user) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $owner = $account->user;
        $initial = mb_substr(trim($owner->lastname), 0, 1);

        return response()->json([
            'account_id' => $account->account_id,
            'holder_name' => trim($owner->firstname . ' ' . $initial . ($initial !== '' ? '.' : '')),
            'is_own' => (string) $account->user_id === (string) auth()->user()->userid,
        ], 200);
    }

    // ชื่อเจ้าของบัญชีแบบย่อ (ชื่อจริงเต็ม + อักษรแรกของนามสกุล) ใช้กับชื่ออีกฝั่งในสลิป
    private function maskedName($user): ?string
    {
        if (!$user) {
            return null;
        }
        $initial = mb_substr(trim($user->lastname), 0, 1);
        return trim($user->firstname . ' ' . $initial . ($initial !== '' ? '.' : ''));
    }

    // สลิป/ใบเสร็จของธุรกรรม ดูได้เฉพาะเจ้าของบัญชีที่ธุรกรรมนั้นสังกัด
    public function receipt($id)
    {
        $user = auth()->user();

        // id เป็น UUID: ถ้ารูปแบบไม่ใช่ UUID ให้ตอบ 404 เลย (ไม่งั้น Postgres โยน error 500)
        if (!Str::isUuid((string) $id)) {
            return response()->json(['error' => 'Receipt not found'], 404);
        }

        $transaction = Transaction::with(['bankAccount.user', 'toBankAccount.user'])->find($id);
        if (!$transaction || (string) $transaction->bankAccount?->user_id !== (string) $user->userid) {
            return response()->json(['error' => 'Receipt not found'], 404);
        }

        $owner = $transaction->bankAccount->user;
        $other = $transaction->toBankAccount;

        return response()->json([
            'reference' => strtoupper(substr(str_replace('-', '', $transaction->id), 0, 12)),
            'transaction_id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance' => $transaction->balance,
            'created_at' => $transaction->created_at,
            'account' => [
                'account_id' => $transaction->account_id,
                'holder_name' => trim($owner->firstname . ' ' . $owner->lastname),
            ],
            'counterparty' => $other ? [
                'account_id' => $other->account_id,
                'holder_name' => $this->maskedName($other->user),
            ] : null,
        ], 200);
    }

    // ยอดที่โอนออกไปแล้ววันนี้ (นับเฉพาะ type = transfer ของทุกบัญชีของผู้ใช้)
    private function transferredToday($user): string
    {
        $accountIds = $user->bankAccounts()->pluck('account_id');

        // เริ่มนับจากเที่ยงคืน หรือเวลาที่แอดมินรีเซตวงเงินล่าสุด (แล้วแต่อันไหนใหม่กว่า)
        $start = now()->startOfDay();
        if ($user->transfer_limit_reset_at && $user->transfer_limit_reset_at->gt($start)) {
            $start = $user->transfer_limit_reset_at;
        }

        $sum = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->where('created_at', '>=', $start)
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    // วงเงินโอนรายวัน: วงเงิน / ใช้ไปแล้ว / คงเหลือ / เวลารีเซต
    public function transferLimit()
    {
        $used = $this->transferredToday(auth()->user());

        return response()->json([
            'limit' => self::DAILY_TRANSFER_LIMIT,
            'used' => $used,
            'remaining' => bcsub(self::DAILY_TRANSFER_LIMIT, $used, 2),
            'resets_at' => now()->addDay()->startOfDay()->toIso8601String(),
        ], 200);
    }

    public function getallBalance(Request $request)
    {
        $user = auth()->user();

        $accounts = $user->bankAccounts()->get();

        if ($accounts->isEmpty()) {
            return response()->json(['error' => 'No bank account found'], 404);
        }

        return response()->json([
            'message' => 'Account details retrieved successfully',
            'user' => [
                'userid' => $user->userid,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
            ],
            'bank_accounts' => $accounts,
        ], 200);
    }

    public function getBal(Request $request)
    {
        $user = auth()->user();

        $account = $user->bankAccounts()->orderBy('account_id')->first();
        if (!$account) {
            return response()->json(['error' => 'No bank account found'], 404);
        }

        return response()->json([
            'message' => 'Balance retrieved successfully',
            'balance' => $account->balance,
        ], 200);
    }

    public function getTransaction(Request $request)
    {
        $user = auth()->user();

        $account = $user->bankAccounts()->orderBy('account_id')->first();
        if (!$account) {
            return response()->json(['error' => 'No bank account found'], 404);
        }

        // ตัวกรอง: ประเภท + ช่วงวันที่ (นับทั้งวันของวันที่ from ถึงสิ้นวันของ to ตามเวลา app timezone) + แบ่งหน้า
        $filters = $request->validate([
            'type' => 'nullable|in:deposit,withdraw,transfer,receive',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        $query = $account->transactions();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filters['from'])->startOfDay());
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filters['to'])->endOfDay());
        }

        // ยอดรวมแยกประเภทของ "ทั้งชุดที่กรอง" (ไม่ใช่แค่หน้านี้) ใช้กับกราฟวงกลม
        $totals = (clone $query)->reorder()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $page = $query->orderBy('created_at', 'desc')
            ->paginate((int) ($filters['per_page'] ?? 10));

        return response()->json([
            'message' => 'Transactions retrieved successfully',
            'transactions' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'totals' => [
                'deposit' => number_format((float) ($totals['deposit'] ?? 0), 2, '.', ''),
                'withdraw' => number_format((float) ($totals['withdraw'] ?? 0), 2, '.', ''),
                'transfer' => number_format((float) ($totals['transfer'] ?? 0), 2, '.', ''),
                'receive' => number_format((float) ($totals['receive'] ?? 0), 2, '.', ''),
            ],
        ], 200);
    }
}
