<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedAccount extends Model
{
    protected $fillable = ['user_id', 'account_id', 'nickname'];

    protected $casts = ['account_id' => 'integer'];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'account_id', 'account_id');
    }
}
