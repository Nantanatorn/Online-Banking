<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Database\QueryException;

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
                'pin' => Hash::make(trim($request->pin)), // ✅ Hash ค่า PIN
            ]);

            // 🔹 4. ส่ง Response กลับ
            return response()->json([
                'message' => 'User registered successfully!',
                'user' => $user
            ], 201);

        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Database Error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }
    public function login(Request $request)
    {
        // 🔹 ตรวจสอบข้อมูลที่ส่งมา
        $validator = Validator::make($request->all(), [
            'userid' => 'required|userid',
            'pin' => 'required|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 🔹 ตรวจสอบข้อมูลและสร้าง Token
        $credentials = ['userid' => $request->userid, 'pin' => $request->pin];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token
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
        // 🔹 ดึงข้อมูล User จาก Token
        return response()->json(JWTAuth::parseToken()->authenticate());
    }

}
