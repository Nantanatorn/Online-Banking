<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

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

        $account->balance += $request->amount;
        $account->save();

        return response()->json(['message' => 'Deposit successful', 'balance' => $account->balance]);
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

        $account->balance -= $validated['amount'];
        $account->save();

        return response()->json(['message' => 'withdraw successful', 'balance' => $account->balance]);
    }

    public function tranfer(Request $request)
    {
        $user = auth()->user();

        $fromAccount = $user->bankAccounts()->first();
        if (!$fromAccount) {
            return response()->json(['error' => 'No bank account found'], 404);
        }
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'target_account_id' => 'required|numeric|exists:bank_accounts,account_id'
        ]);

        if ($fromAccount->balance < $validated['amount']) {
            return response()->json(['error' => 'Insufficient balance'], 400);
        }

        $toAccount = BankAccount::where('account_id', $validated['target_account_id'])->first();

        try {
            \DB::beginTransaction();
    
            $fromAccount->balance -= $validated['amount'];
            $fromAccount->save();
    
            $toAccount->balance += $validated['amount'];
            $toAccount->save();
    
            Transaction::create([
                'account_id' => $fromAccount->account_id,
                'type' => 'transfer',
                'amount' => $validated['amount'],
                'to_account_id' => $toAccount->account_id,
            ]);
    
            \DB::commit(); 
        } catch (\Exception $e) {
            \DB::rollBack(); 
            return response()->json(['error' => 'Transfer failed: ' . $e->getMessage()], 500);
        }
    
        return response()->json([ 'message' => 'Transfer successful', 'from_balance' => $fromAccount->balance, 'to_balance' => $toAccount->balance, ]);
    }
}
