<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

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
}


