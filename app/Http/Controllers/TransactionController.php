<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function deposit(Request $request)
    {
        $user = auth()->user();
    
        
        $account = $user->bankAccounts()->first();
        if (!$account) {
            return response()->json(['error' => 'No bank account found'], 404);
        }
    
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);
    
        try {
            \DB::beginTransaction(); // 🔹 เริ่ม Transaction
    
            
            $account->balance += $validated['amount'];
            $account->save();
    
            
            Transaction::create([
                'id' => \Str::uuid(), 
                'account_id' => $account->account_id, 
                'type' => 'deposit', 
                'amount' => $validated['amount'],
                'balance' => $account->balance, 
                'to_account_id' => null, 
            ]);
    
            \DB::commit(); // 🔹 บันทึกข้อมูลทั้งหมด
    
            return response()->json([
                'message' => 'Deposit successful',
                'balance' => $account->balance
            ], 200);
    
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Deposit failed: ' . $e->getMessage()], 500);
        }
    }
    
    public function withdraw(Request $request)
{
    $user = auth()->user();

    
    $account = $user->bankAccounts()->first();
    if (!$account) {
        return response()->json(['error' => 'No bank account found'], 404);
    }

    
    $validated = $request->validate([
        'amount' => 'required|numeric|min:1',
    ]);

    
    if ($account->balance < $validated['amount']) {
        return response()->json(['error' => 'Insufficient balance'], 400);
    }

    try {
        \DB::beginTransaction(); // 🔹 เริ่ม Transaction

        
        $account->balance -= $validated['amount'];
        $account->save();

       
        Transaction::create([
            'id' => \Str::uuid(),
            'account_id' => $account->account_id,
            'type' => 'withdraw',
            'amount' => $validated['amount'],
            'balance' => $account->balance, // 🆕 บันทึกยอดคงเหลือ
            'to_account_id' => null,
        ]);

        \DB::commit(); // 🔹 บันทึกข้อมูลทั้งหมด

        return response()->json([
            'message' => 'Withdraw successful',
            'balance' => $account->balance
        ], 200);

    } catch (\Exception $e) {
        \DB::rollBack();
        return response()->json(['error' => 'Withdraw failed: ' . $e->getMessage()], 500);
    }
}


public function transfer(Request $request)
{
    $user = auth()->user();

    // ✅ ตรวจสอบข้อมูลที่ส่งมา
    $validated = $request->validate([
        'amount' => 'required|numeric|min:1',
        'source_account_id' => 'required|numeric|exists:bank_accounts,account_id',
        'target_account_id' => 'required|numeric|exists:bank_accounts,account_id'
    ]);

    // ❌ ห้ามโอนเข้าบัญชีตัวเอง
    if ($validated['source_account_id'] == $validated['target_account_id']) {
        return response()->json(['error' => 'Cannot transfer to the same account'], 400);
    }

    DB::beginTransaction();

    try {
        // ✅ ค้นหาบัญชีต้นทางและล็อกกันการเปลี่ยนแปลงขณะโอน
        $fromAccount = BankAccount::where('account_id', $validated['source_account_id'])
            ->where('user_id', $user->id) // ป้องกันโอนเงินจากบัญชีที่ไม่ได้เป็นเจ้าของ
            ->lockForUpdate()
            ->first();

        if (!$fromAccount) {
            DB::rollBack();
            return response()->json(['error' => 'Unauthorized access to source account'], 403);
        }

        // ✅ ค้นหาบัญชีปลายทาง
        $toAccount = BankAccount::where('account_id', $validated['target_account_id'])
            ->lockForUpdate()
            ->first();

        if (!$toAccount) {
            DB::rollBack();
            return response()->json(['error' => 'Target account not found'], 404);
        }

        // ✅ ตรวจสอบยอดเงิน
        if ($fromAccount->balance < $validated['amount']) {
            DB::rollBack();
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        // ✅ ทำธุรกรรมโอนเงิน
        $fromAccount->balance -= $validated['amount'];
        $fromAccount->save();

        $toAccount->balance += $validated['amount'];
        $toAccount->save();

        // ✅ บันทึกธุรกรรมฝั่งผู้โอน
        Transaction::create([
            'id' => Str::uuid(),
            'account_id' => $fromAccount->account_id,
            'type' => 'transfer',
            'amount' => $validated['amount'],
            'to_account_id' => $toAccount->account_id,
        ]);

        // ✅ บันทึกธุรกรรมฝั่งผู้รับ
        Transaction::create([
            'id' => Str::uuid(),
            'account_id' => $toAccount->account_id,
            'type' => 'receive',
            'amount' => $validated['amount'],
            'to_account_id' => $fromAccount->account_id,
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Transfer successful',
            'from_balance' => $fromAccount->balance,
            'to_balance' => $toAccount->balance,
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error("Transfer failed: " . $e->getMessage());
        return response()->json(['error' => 'Transfer failed. Please try again.'], 500);
    }
}




    public function getallBalance(Request $request)
    {
        // ดึงข้อมูลผู้ใช้ที่ล็อกอินอยู่
        $user = auth()->user();

        // ค้นหาบัญชีธนาคารของผู้ใช้ (ทั้งหมด)
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
            'bank_accounts' => $accounts
        ], 200);
    }

    public function getBal(Request $request)
    {
        // ดึงข้อมูลผู้ใช้ที่ล็อกอินอยู่
        $user = auth()->user();

        // ค้นหาบัญชีธนาคารของผู้ใช้
        $account = $user->bankAccounts()->first();
        if (!$account) {
            return response()->json(['error' => 'No bank account found'], 404);
        }

        return response()->json([
            'message' => 'Balance retrieved successfully',
            'balance' => $account->balance
        ], 200);
    }

    public function getTransaction(Request $request)
    {
        
        $user = auth()->user();

        // ค้นหาบัญชีธนาคารของผู้ใช้
        $account = $user->bankAccounts()->first();
        if (!$account) {
            return response()->json(['error' => 'No bank account found'], 404);
        }

        
        $transactions = $account->transactions()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Transactions retrieved successfully',
            'transactions' => $transactions
        ], 200);
    }

}
