<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\BankAccount;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Database\QueryException;
use Exception;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            // 🔹 1. ตรวจสอบข้อมูลก่อนสมัคร
            $validator = Validator::make($request->all(), [
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                'idcard' => 'required|string|size:13|unique:users,idcard',
                'phone' => 'required|string|max:15|unique:users,phone',
                'email' => 'required|string|email|max:255|unique:users,email',
                'address' => 'required|string',
                'gender' => 'required|in:male,female,other',
                'pin' => 'required|string|min:4|max:6',
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
                'account_id' => random_int(1000000, 9999999), //  สุ่มเลขบัญชี 7 หลัก
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

        } catch (QueryException $e) {
            \DB::rollBack(); 
            return response()->json([
                'error' => 'Database Error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'error' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userid' => 'required|string|size:13',
            'pin' => 'required|string|min:4|max:6',
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

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
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
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
}

}
