<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions'; // เชื่อมกับตาราง `transactions`
    protected $primaryKey = 'id'; // ใช้ `id` เป็น Primary Key
    public $incrementing = false; // ปิด Auto-Increment
    protected $keyType = 'string'; // ใช้ UUID เป็น Primary Key

    // เก็บเวลาระดับไมโครวินาที ให้ประวัติเรียงถูกเมื่อทำหลายรายการในวินาทีเดียวกัน
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id', 'account_id', 'type', 'amount', 'balance', 'to_account_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected static function booted()
    {
        // ทุกธุรกรรมใหม่ (ฝาก/ถอน/โอน/รับ) สร้างแจ้งเตือนให้เจ้าของบัญชี
        static::created(function (Transaction $transaction) {
            \App\Services\TransactionNotifier::notify($transaction);
        });
    }

    // 🔹 ความสัมพันธ์กับบัญชีธนาคาร (ธุรกรรมแต่ละรายการเป็นของ 1 บัญชี)
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'account_id', 'account_id');
    }

    // 🔹 ความสัมพันธ์กับบัญชีปลายทาง (ถ้าเป็นการโอนเงิน)
    public function toBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'to_account_id', 'account_id');
    }
}
