<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // กันพลาด: RefreshDatabase ล้างทุกตาราง ห้ามรันบนฐานจริง
        $db = config('database.connections.' . config('database.default') . '.database');
        if (!str_ends_with((string) $db, '_test')) {
            $this->fail("Refusing to run on non-test database: {$db}");
        }
    }

    private function register(string $id = '1111111111111', array $over = [])
    {
        return $this->postJson('/api/register', array_merge([
            'firstname' => 'Somchai', 'lastname' => 'Jaidee', 'idcard' => $id,
            'phone' => '08' . substr($id, 0, 8), 'email' => "u{$id}@test.com",
            'address' => 'Bangkok', 'gender' => 'male', 'pin' => '123456',
        ], $over));
    }

    /** สมัคร + ล็อกอิน คืน [headers, account_id] */
    private function actor(string $id = '1111111111111', float $balance = 0): array
    {
        $this->register($id)->assertStatus(201);
        $l = $this->postJson('/api/login', ['userid' => $id, 'pin' => '123456'])->assertOk();
        $acc = BankAccount::where('user_id', $id)->first();
        if ($balance) {
            $acc->update(['balance' => $balance]);
        }
        return [['Authorization' => 'Bearer ' . $l->json('token')], $acc->account_id];
    }

    // ---------- Register ----------
    public function test_register_success(): void
    {
        $r = $this->register()->assertStatus(201);
        $this->assertEquals(0, $r->json('bank_account.balance'));
        $this->assertDatabaseHas('users', ['userid' => '1111111111111']);
    }

    public function test_register_duplicate_idcard(): void
    {
        $this->register();
        $this->register('1111111111111', ['phone' => '0999999999', 'email' => 'x@x.com'])->assertStatus(422);
    }

    public function test_register_duplicate_email(): void
    {
        $this->register();
        $this->register('2222222222222', ['email' => 'u1111111111111@test.com'])->assertStatus(422);
    }

    public function test_register_missing_fields(): void
    {
        $this->postJson('/api/register', [])->assertStatus(422);
    }

    public function test_register_idcard_wrong_length(): void
    {
        $this->register('123')->assertStatus(422);
    }

    public function test_register_does_not_leak_pin_hash(): void
    {
        $this->register()->assertJsonMissingPath('user.pin');
    }

    // ---------- Login / Auth ----------
    public function test_login_success(): void
    {
        $this->register();
        $this->postJson('/api/login', ['userid' => '1111111111111', 'pin' => '123456'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['account_id']]);
    }

    public function test_login_wrong_pin(): void
    {
        $this->register();
        $this->postJson('/api/login', ['userid' => '1111111111111', 'pin' => '9999'])->assertStatus(401);
    }

    public function test_login_unknown_user(): void
    {
        $this->postJson('/api/login', ['userid' => '9999999999999', 'pin' => '123456'])->assertStatus(401);
    }

    public function test_user_endpoint_requires_token(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_logout_invalidates_token(): void
    {
        [$h] = $this->actor();
        $this->postJson('/api/logout', [], $h)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', $h)->assertStatus(401);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->register();
        $last = null;
        for ($i = 0; $i < 20; $i++) {
            $last = $this->postJson('/api/login', ['userid' => '1111111111111', 'pin' => '0000'])->status();
        }
        $this->assertSame(429, $last, 'brute-force ของ PIN 4 หลักไม่ถูกจำกัด');
    }

    // ---------- Deposit ----------
    public function test_deposit_success(): void
    {
        [$h, $acc] = $this->actor();
        $r = $this->postJson('/api/deposit', ['amount' => 500], $h)->assertOk();
        $this->assertEquals(500, $r->json('balance'));
        $this->assertEquals(500, BankAccount::find($acc)->balance);
    }

    public function test_deposit_negative(): void
    {
        [$h] = $this->actor();
        $this->postJson('/api/deposit', ['amount' => -5], $h)->assertStatus(422);
    }

    public function test_deposit_zero(): void
    {
        [$h] = $this->actor();
        $this->postJson('/api/deposit', ['amount' => 0], $h)->assertStatus(422);
    }

    public function test_deposit_more_than_two_decimals_rejected(): void
    {
        [$h] = $this->actor();
        $this->postJson('/api/deposit', ['amount' => 1.005], $h)->assertStatus(422);
    }

    public function test_deposit_requires_auth(): void
    {
        $this->postJson('/api/deposit', ['amount' => 100])->assertStatus(401);
    }

    public function test_deposit_huge_amount_rejected(): void
    {
        [$h] = $this->actor();
        $this->postJson('/api/deposit', ['amount' => 1e20], $h)->assertStatus(422);
    }

    // ---------- Withdraw ----------
    public function test_withdraw_success(): void
    {
        [$h, $acc] = $this->actor('1111111111111', 1000);
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 300], $h)->assertOk();
        $this->assertEquals(700, BankAccount::find($acc)->balance);
    }

    public function test_withdraw_insufficient(): void
    {
        [$h] = $this->actor('1111111111111', 100);
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 101], $h)->assertStatus(400);
    }

    public function test_withdraw_exact_balance(): void
    {
        [$h, $acc] = $this->actor('1111111111111', 100);
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 100], $h)->assertOk();
        $this->assertEquals(0, BankAccount::find($acc)->balance);
    }

    public function test_withdraw_rejects_bad_decimals_and_huge_amounts(): void
    {
        [$h, $acc] = $this->actor('1111111111111', 1000);
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 1.005], $h)->assertStatus(422);
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 1e20], $h)->assertStatus(422);
        $this->assertEquals(1000, BankAccount::find($acc)->balance);
    }

    public function test_transfer_rejects_bad_decimals_and_huge_amounts(): void
    {
        [$h1, $a1, , $a2] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 1.005, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertStatus(422);
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 1e20, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertStatus(422);
    }

    public function test_transfer_records_running_balance_on_both_sides(): void
    {
        [$h1, $a1, , $a2] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 100, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertOk();
        $this->assertEquals(900, Transaction::where('account_id', $a1)->first()->balance);
        $this->assertEquals(100, Transaction::where('account_id', $a2)->first()->balance);
    }

    public function test_withdraw_requires_auth(): void
    {
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 100])->assertStatus(401);
    }

    // ---------- Transfer ----------
    private function two(float $bal = 1000): array
    {
        [$h1, $a1] = $this->actor('1111111111111', $bal);
        [$h2, $a2] = $this->actor('2222222222222');
        return [$h1, $a1, $h2, $a2];
    }

    public function test_transfer_success(): void
    {
        [$h1, $a1, , $a2] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 250, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)
            ->assertOk();
        $this->assertEquals(750, BankAccount::find($a1)->balance);
        $this->assertEquals(250, BankAccount::find($a2)->balance);
    }

    public function test_transfer_insufficient(): void
    {
        [$h1, $a1, , $a2] = $this->two(100);
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 500, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)
            ->assertStatus(400);
    }

    public function test_transfer_to_same_account(): void
    {
        [$h1, $a1] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 10, 'source_account_id' => $a1, 'target_account_id' => $a1], $h1)
            ->assertStatus(400);
    }

    public function test_transfer_target_not_found(): void
    {
        [$h1, $a1] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 10, 'source_account_id' => $a1, 'target_account_id' => 1], $h1)
            ->assertStatus(422);
    }

    public function test_transfer_from_someone_elses_account_forbidden(): void
    {
        [$h1, $a1, , $a2] = $this->two();
        // user2 พยายามโอนจากบัญชีของ user1
        [, , $h2] = [null, null, $this->postJson('/api/login', ['userid' => '2222222222222', 'pin' => '123456'])->json('token')];
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 10, 'source_account_id' => $a1, 'target_account_id' => $a2],
            ['Authorization' => "Bearer $h2"])->assertStatus(403);
        $this->assertEquals(1000, BankAccount::find($a1)->balance);
    }

    public function test_transfer_records_both_sides(): void
    {
        [$h1, $a1, , $a2] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 100, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertOk();
        $this->assertSame(1, Transaction::where('account_id', $a1)->count());
        $this->assertSame(1, Transaction::where('account_id', $a2)->count());
    }

    public function test_transfer_conserves_total_money(): void
    {
        [$h1, $a1, , $a2] = $this->two();
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 333.33, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1);
        $sum = BankAccount::whereIn('account_id', [$a1, $a2])->sum('balance');
        $this->assertEquals(1000, $sum);
    }

    public function test_transfer_requires_auth(): void
    {
        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 1, 'source_account_id' => 1, 'target_account_id' => 2])->assertStatus(401);
    }

    public function test_receive_transaction_type_persists_in_db(): void
    {
        [, $a1] = $this->two();
        Transaction::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'account_id' => $a1,
            'type' => 'receive', 'amount' => 1, 'to_account_id' => null]);
        $this->assertDatabaseHas('transactions', ['type' => 'receive']);
    }

    // ---------- Query ----------
    public function test_balance_endpoint(): void
    {
        [$h] = $this->actor('1111111111111', 42);
        $r = $this->getJson('/api/bal', $h)->assertOk();
        $this->assertEquals(42, $r->json('balance'));
    }

    public function test_history_is_newest_first_and_has_running_balance(): void
    {
        [$h] = $this->actor();
        $this->postJson('/api/deposit', ['amount' => 100], $h);
        $this->travel(1)->minutes();
        $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 30], $h);
        $r = $this->getJson('/api/transactionhis', $h)->assertOk();
        $this->assertSame('withdraw', $r->json('transactions.0.type'));
        $this->assertNotNull($r->json('transactions.0.balance'), 'ประวัติไม่มียอดคงเหลือ');
    }

    public function test_history_only_own_transactions(): void
    {
        [$h1, , $h2] = $this->two();
        $this->postJson('/api/deposit', ['amount' => 5], $h1);
        $this->assertSame(1, Transaction::count());
        $user2 = \App\Models\User::find('2222222222222');
        $this->actingAs($user2, 'api');
        $this->assertCount(0, $this->getJson('/api/transactionhis')->json('transactions'));
    }
}
