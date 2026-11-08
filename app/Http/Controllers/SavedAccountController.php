<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\SavedAccount;
use Illuminate\Http\Request;

class SavedAccountController extends Controller
{
    private function present(SavedAccount $saved): array
    {
        $owner = $saved->bankAccount?->user;

        return [
            'id' => $saved->id,
            'account_id' => $saved->account_id,
            'nickname' => $saved->nickname,
            'holder_name' => $owner ? trim("{$owner->firstname} {$owner->lastname}") : null,
        ];
    }

    public function index()
    {
        $items = SavedAccount::with('bankAccount.user')
            ->where('user_id', auth()->user()->userid)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($s) => $this->present($s));

        return response()->json(['saved_accounts' => $items], 200);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'account_id' => 'required|integer|exists:bank_accounts,account_id',
            'nickname' => 'nullable|string|max:50',
        ]);

        // ห้ามบันทึกบัญชีของตัวเอง
        if (BankAccount::where('account_id', $validated['account_id'])->where('user_id', $user->userid)->exists()) {
            return response()->json(['error' => 'Cannot save your own account'], 400);
        }

        $saved = SavedAccount::updateOrCreate(
            ['user_id' => $user->userid, 'account_id' => $validated['account_id']],
            ['nickname' => isset($validated['nickname']) ? trim($validated['nickname']) ?: null : null]
        );

        return response()->json([
            'message' => 'Account saved',
            'saved_account' => $this->present($saved->load('bankAccount.user')),
        ], 201);
    }

    public function destroy($id)
    {
        // id ที่ไม่ใช่ตัวเลข/เกินช่วง bigint ทำให้ Postgres error (500) จึงตอบ 404 ก่อน
        if (!preg_match('/^\d{1,18}$/', (string) $id)) {
            return response()->json(['error' => 'Saved account not found'], 404);
        }

        $deleted = SavedAccount::where('id', $id)->where('user_id', auth()->user()->userid)->delete();

        if (!$deleted) {
            return response()->json(['error' => 'Saved account not found'], 404);
        }

        return response()->json(['message' => 'Saved account removed'], 200);
    }
}
