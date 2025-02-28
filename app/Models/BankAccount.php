<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'bank_accounts'; // เชื่อมกับตาราง `bank_accounts`
    protected $primaryKey = 'account_id'; // ใช้ `account_id` เป็น Primary Key
    public $incrementing = false; // ปิด Auto-Increment
    protected $keyType = 'int'; // `account_id` เป็น `bigInteger`

    protected $fillable = ['account_id', 'user_id', 'balance', 'interest'];

    // 🔹 ความสัมพันธ์กับผู้ใช้ (บัญชี 1 บัญชี เป็นของผู้ใช้ 1 คน)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'userid');
    }

    protected static function boot()
    {
        parent::boot();

        // 🔹 กำหนดเลข `account_id` ให้เป็นเลขสุ่ม (7-9 หลัก)
        static::creating(function ($account) {
            do {
                $random_id = random_int(1000000, 9999999); // สุ่มเลข 7 หลัก
            } while (BankAccount::where('account_id', $random_id)->exists());

            $account->account_id = $random_id;
        });
    }
}

