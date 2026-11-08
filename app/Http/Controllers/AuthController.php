<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\BankAccount;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Exception;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            // อีเมลเทียบแบบไม่สนตัวพิมพ์ (unique ใน Postgres เป็น case-sensitive)
            if (is_string($request->email)) {
                $request->merge(['email' => mb_strtolower(trim($request->email))]);
            }

            // 🔹 1. ตรวจสอบข้อมูลก่อนสมัคร
            $validator = Validator::make($request->all(), [
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                'idcard' => 'required|digits:13|unique:users,idcard',
                'phone' => 'required|digits_between:9,15|unique:users,phone',
                'email' => 'required|string|email|max:255|unique:users,email',
                'address' => 'required|string',
                'gender' => 'required|in:male,female,other',
                'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            ]);

            // 🔹 2. ถ้าข้อมูลไม่ผ่าน ส่งข้อความแจ้งเตือนกลับไป
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            \DB::beginTransaction(); //  เริ่ม Transaction

            // 🔹 3. สร้าง User ใหม่
            $user = User::create([
                'userid' => trim($request->idcard), // ใช้ idcard เป็น userid
                'firstname' => trim($request->firstname),
                'lastname' => trim($request->lastname),
                'idcard' => trim($request->idcard),
                'phone' => trim($request->phone),
                'email' => trim($request->email),
                'address' => trim($request->address),
                'gender' => trim($request->gender),
                'pin' => Hash::make(trim($request->pin)), //  Hash ค่า PIN
            ]);

           
            $account = BankAccount::create([
                'user_id' => $user->userid, 
                'balance' => 0, 
                'interest' => 0 * 0.25, 
            ]);

            \DB::commit(); 

            // 🔹 5. ส่ง Response กลับ
            return response()->json([
                'message' => 'User registered successfully!',
                'user' => $user,
                'bank_account' => $account
            ], 201);

        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('Register failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Registration failed. Please try again.'
            ], 500);
        }
    }

    public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'userid' => 'required|digits:13',
        'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
    }

    $user = User::where('userid', $request->userid)->first();

    if (!$user || !Hash::check($request->pin, $user->pin)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    try {
        $token = JWTAuth::fromUser($user);
    } catch (Exception $e) {
        return response()->json(['error' => 'Could not create token'], 500);
    }

    $bankAccount = BankAccount::where('user_id', $user->userid)->orderBy('account_id')->first();
    $accountId = $bankAccount ? $bankAccount->account_id : null;

    return response()->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'userid' => $user->userid,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'account_id' => $accountId, // ✅ ส่ง `account_id` ที่ดึงจาก `bank_accounts`
        ]
    ]);
}




    public function logout(Request $request)
    {
        // 🔹 ลบ Token ออกจากระบบ
        JWTAuth::invalidate(JWTAuth::parseToken());

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function getUser(Request $request)
{
    try {
        $user = JWTAuth::parseToken()->authenticate();
        return response()->json([
            'userid' => $user->userid,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
}

}
