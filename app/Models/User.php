<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users'; // เชื่อมกับตาราง `users`
    protected $primaryKey = 'userid'; // ใช้ `userid` เป็น Primary Key
    public $incrementing = false; // ปิด Auto-Increment
    protected $keyType = 'string'; // `userid` เป็น `string`

    protected $fillable = [
        'userid', 'firstname', 'lastname', 'idcard', 'phone', 'email', 'address', 'gender', 'pin'
    ];

    // 🔹 ความสัมพันธ์กับบัญชีธนาคาร (1 User มีหลายบัญชี)
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class, 'user_id', 'userid');
    }

    // 🔹 กำหนดให้ `userid` มีค่าเท่ากับ `idcard`
    public function setIdcardAttribute($value)
    {
        $this->attributes['idcard'] = $value;
        $this->attributes['userid'] = $value;
    }

    // ✅ เพิ่มเมธอดสำหรับ JWT
    public function getJWTIdentifier()
    {
        return $this->getKey(); // ใช้ `userid` เป็น primary key
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
