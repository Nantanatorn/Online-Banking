<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['userid' => '1234567890123'],
            [
                'firstname' => 'Test',
                'lastname' => 'User',
                'idcard' => '1234567890123',
                'phone' => '0800000000',
                'email' => 'test@example.com',
                'address' => 'Bangkok',
                'gender' => 'other',
                'pin' => Hash::make('123456'),
            ]
        );

        BankAccount::firstOrCreate(
            ['user_id' => $user->userid],
            ['balance' => 0, 'interest' => 0]
        );
    }
}
