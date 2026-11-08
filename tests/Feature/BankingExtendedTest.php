<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\SavedAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * เทสเสริม: saved-accounts, PIN ของถอน/โอน, balance endpoints, หน้าเว็บ, edge cases
 * ต้องรันบน Postgres: DB_CONNECTION=pgsql DB_DATABASE=online_banking_test php artisan test
 */
class BankingExtendedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $db = config('database.connections.' . config('database.default') . '.database');
        if (!str_ends_with((string) $db, '_test')) {
            $this->fail("Refusing to run on non-test database: {$db}");
        }
        $this->assertSame('pgsql', config('database.default'), 'These tests must run on PostgreSQL');
    }


    /** ล้าง guard ที่ cache user ไว้ ให้แต่ละ request ใน test เดียวกันยืนยันตัวตนจาก token ของตัวเอง */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app) {
            $this->app['auth']->forgetGuards();
            foreach (['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'] as $k) {
                $this->app->forgetInstance($k);
            }
        }
        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
    private function actor(string $id, float $balance = 0): array
    {
        $this->postJson('/api/register', [
            'firstname' => 'First' . substr($id, -2), 'lastname' => 'Last', 'idcard' => $id,
            'phone' => '08' . substr($id, -8), 'email' => "u{$id}@test.com",
            'address' => 'Bangkok', 'gender' => 'male', 'pin' => '123456',
        ])->assertStatus(201);
        $token = $this->postJson('/api/login', ['userid' => $id, 'pin' => '123456'])->assertOk()->json('token');
        $acc = BankAccount::where('user_id', $id)->first();
        if ($balance) {
            $acc->update(['balance' => $balance]);
        }
        return [['Authorization' => 'Bearer ' . $token], $acc->account_id];
    }

    // ---------- Saved accounts ----------
    public function test_saved_accounts_require_auth(): void
    {
        $this->getJson('/api/saved-accounts')->assertStatus(401);
        $this->postJson('/api/saved-accounts', ['account_id' => 1])->assertStatus(401);
        $this->deleteJson('/api/saved-accounts/1')->assertStatus(401);
    }

    public function test_saved_account_create_list_delete(): void
    {
        [$ha] = $this->actor('1000000000001');
        [, $bId] = $this->actor('1000000000002');

        $r = $this->postJson('/api/saved-accounts', ['account_id' => $bId, 'nickname' => '  Mom  '], $ha)->assertStatus(201);
        $r->assertJsonPath('saved_account.account_id', $bId);
        $r->assertJsonPath('saved_account.holder_name', 'First02 Last');
        $savedId = $r->json('saved_account.id');

        $this->getJson('/api/saved-accounts', $ha)->assertOk()->assertJsonCount(1, 'saved_accounts');
        $this->assertSame('Mom', SavedAccount::find($savedId)->nickname);

        $this->deleteJson("/api/saved-accounts/{$savedId}", [], $ha)->assertOk();
        $this->getJson('/api/saved-accounts', $ha)->assertJsonCount(0, 'saved_accounts');
    }

    public function test_saved_account_duplicate_updates_instead_of_duplicating(): void
    {
        [$ha] = $this->actor('1000000000001');
        [, $bId] = $this->actor('1000000000002');

        $this->postJson('/api/saved-accounts', ['account_id' => $bId, 'nickname' => 'A'], $ha)->assertStatus(201);
        $this->postJson('/api/saved-accounts', ['account_id' => $bId, 'nickname' => 'B'], $ha)->assertSuccessful();

        $this->assertSame(1, SavedAccount::count());
        $this->assertSame('B', SavedAccount::first()->nickname);
    }

    public function test_saved_account_blank_nickname_becomes_null(): void
    {
        [$ha] = $this->actor('1000000000001');
        [, $bId] = $this->actor('1000000000002');
        $this->postJson('/api/saved-accounts', ['account_id' => $bId, 'nickname' => '   '], $ha)->assertStatus(201);
        $this->assertNull(SavedAccount::first()->nickname);
    }

    public function test_saved_account_cannot_save_own_account(): void
    {
        [$ha, $aId] = $this->actor('1000000000001');
        $this->postJson('/api/saved-accounts', ['account_id' => $aId], $ha)->assertStatus(400);
    }

    public function test_saved_account_validation(): void
    {
        [$ha] = $this->actor('1000000000001');
        $this->postJson('/api/saved-accounts', [], $ha)->assertStatus(422);
        $this->postJson('/api/saved-accounts', ['account_id' => 999999], $ha)->assertStatus(422);
        $this->postJson('/api/saved-accounts', ['account_id' => 'abc'], $ha)->assertStatus(422);
        [, $bId] = $this->actor('1000000000002');
        $this->postJson('/api/saved-accounts', ['account_id' => $bId, 'nickname' => str_repeat('x', 51)], $ha)->assertStatus(422);
    }

    public function test_saved_account_huge_account_id_is_422_not_500(): void
    {
        [$ha] = $this->actor('1000000000001');
        // เกินช่วง bigint ของ Postgres
        $this->postJson('/api/saved-accounts', ['account_id' => '99999999999999999999'], $ha)->assertStatus(422);
        // อยู่ในช่วง bigint แต่ไม่มีบัญชี
        $this->postJson('/api/saved-accounts', ['account_id' => '9000000000000000000'], $ha)->assertStatus(422);
    }

    public function test_saved_account_delete_nonexistent_and_non_numeric_id(): void
    {
        [$ha] = $this->actor('1000000000001');
        $this->deleteJson('/api/saved-accounts/99999', [], $ha)->assertStatus(404);
        // Postgres จะ error ถ้าเอา 'abc' ไปเทียบ bigint — ต้องไม่เป็น 500
        $this->deleteJson('/api/saved-accounts/abc', [], $ha)->assertStatus(404);
    }

    public function test_saved_account_isolated_between_users(): void
    {
        [$ha] = $this->actor('1000000000001');
        [$hb] = $this->actor('1000000000002');
        [, $cId] = $this->actor('1000000000003');

        $savedId = $this->postJson('/api/saved-accounts', ['account_id' => $cId], $ha)->json('saved_account.id');

        $this->getJson('/api/saved-accounts', $hb)->assertJsonCount(0, 'saved_accounts');
        $this->deleteJson("/api/saved-accounts/{$savedId}", [], $hb)->assertStatus(404);
        $this->assertSame(1, SavedAccount::count());
    }

    // ---------- PIN on withdraw / transfer ----------
    public function test_withdraw_wrong_pin_is_forbidden_and_balance_untouched(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 500);
        $this->postJson('/api/withdraw', ['amount' => 100, 'pin' => '000000'], $ha)->assertStatus(403);
        $this->assertEquals(500, BankAccount::find($aId)->balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_withdraw_requires_pin_field(): void
    {
        [$ha] = $this->actor('1000000000001', 500);
        $this->postJson('/api/withdraw', ['amount' => 100], $ha)->assertStatus(422);
        $this->postJson('/api/withdraw', ['amount' => 100, 'pin' => 'abcdef'], $ha)->assertStatus(422);
    }

    public function test_withdraw_pin_is_rate_limited_after_five_failures(): void
    {
        RateLimiter::clear('txn-pin:1000000000001');
        [$ha, $aId] = $this->actor('1000000000001', 500);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/withdraw', ['amount' => 10, 'pin' => '000000'], $ha)->assertStatus(403);
        }
        // ครั้งที่ 6 ต่อให้ PIN ถูกก็ต้องโดนบล็อก
        $this->postJson('/api/withdraw', ['amount' => 10, 'pin' => '123456'], $ha)->assertStatus(429);
        $this->assertEquals(500, BankAccount::find($aId)->balance);
        RateLimiter::clear('txn-pin:1000000000001');
    }

    public function test_correct_pin_resets_failure_counter(): void
    {
        RateLimiter::clear('txn-pin:1000000000001');
        [$ha] = $this->actor('1000000000001', 500);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/withdraw', ['amount' => 10, 'pin' => '000000'], $ha)->assertStatus(403);
        }
        $this->postJson('/api/withdraw', ['amount' => 10, 'pin' => '123456'], $ha)->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/withdraw', ['amount' => 10, 'pin' => '000000'], $ha)->assertStatus(403);
        }
        RateLimiter::clear('txn-pin:1000000000001');
    }

    public function test_transfer_wrong_pin_is_forbidden_and_nothing_moves(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 500);
        [, $bId] = $this->actor('1000000000002');
        $this->postJson('/api/transfer', [
            'amount' => 100, 'source_account_id' => $aId, 'target_account_id' => $bId, 'pin' => '999999',
        ], $ha)->assertStatus(403);
        $this->assertEquals(500, BankAccount::find($aId)->balance);
        $this->assertEquals(0, BankAccount::find($bId)->balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_transfer_missing_fields_are_422(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 500);
        $this->postJson('/api/transfer', ['amount' => 10, 'source_account_id' => $aId, 'pin' => '123456'], $ha)->assertStatus(422);
        $this->postJson('/api/transfer', ['amount' => 10, 'source_account_id' => $aId, 'target_account_id' => $aId], $ha)->assertStatus(422);
    }

    public function test_transfer_huge_account_id_is_422_not_500(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 500);
        $this->postJson('/api/transfer', [
            'amount' => 10, 'source_account_id' => $aId, 'target_account_id' => '99999999999999999999', 'pin' => '123456',
        ], $ha)->assertStatus(422);
    }

    public function test_rolled_back_transfer_leaves_no_partial_state(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 50);
        [, $bId] = $this->actor('1000000000002', 10);
        $this->postJson('/api/transfer', [
            'amount' => 100, 'source_account_id' => $aId, 'target_account_id' => $bId, 'pin' => '123456',
        ], $ha)->assertStatus(400);
        $this->assertEquals(50, BankAccount::find($aId)->balance);
        $this->assertEquals(10, BankAccount::find($bId)->balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_transfer_cents_precision(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 100.10);
        [, $bId] = $this->actor('1000000000002', 0.20);
        $this->postJson('/api/transfer', [
            'amount' => 33.33, 'source_account_id' => $aId, 'target_account_id' => $bId, 'pin' => '123456',
        ], $ha)->assertOk()->assertJsonPath('from_balance', '66.77')->assertJsonPath('to_balance', '33.53');
    }

    // ---------- Balance endpoints ----------
    public function test_bal_returns_balance_and_requires_auth(): void
    {
        $this->getJson('/api/bal')->assertStatus(401);
        $this->getJson('/api/balance')->assertStatus(401);
        $this->getJson('/api/transactionhis')->assertStatus(401);

        [$ha] = $this->actor('1000000000001', 123.45);
        $this->getJson('/api/bal', $ha)->assertOk()->assertJsonPath('balance', '123.45');
    }

    public function test_balance_returns_user_and_accounts_without_pin(): void
    {
        [$ha, $aId] = $this->actor('1000000000001', 10);
        $r = $this->getJson('/api/balance', $ha)->assertOk();
        $r->assertJsonPath('user.userid', '1000000000001');
        $r->assertJsonPath('bank_accounts.0.account_id', $aId);
        $this->assertStringNotContainsString('pin', json_encode($r->json()));
    }

    public function test_user_without_bank_account_gets_404(): void
    {
        [$ha, $aId] = $this->actor('1000000000001');
        BankAccount::where('account_id', $aId)->delete();
        $this->getJson('/api/bal', $ha)->assertStatus(404);
        $this->getJson('/api/balance', $ha)->assertStatus(404);
        $this->getJson('/api/transactionhis', $ha)->assertStatus(404);
        $this->postJson('/api/deposit', ['amount' => 10], $ha)->assertStatus(404);
        $this->postJson('/api/withdraw', ['amount' => 10, 'pin' => '123456'], $ha)->assertStatus(404);
    }

    public function test_history_order_is_deterministic_within_same_second(): void
    {
        [$ha] = $this->actor('1000000000001');
        // ฝากหลายครั้งติดกัน (created_at ความละเอียดระดับวินาที อาจซ้ำกัน)
        foreach ([10, 20, 30, 40, 50] as $amt) {
            $this->postJson('/api/deposit', ['amount' => $amt], $ha)->assertOk();
        }
        $amounts = array_map('floatval', array_column($this->getJson('/api/transactionhis', $ha)->json('transactions'), 'amount'));
        $this->assertSame([50.0, 40.0, 30.0, 20.0, 10.0], $amounts, 'ประวัติต้องเรียงใหม่→เก่า แม้ทำรายการภายในวินาทีเดียวกัน');
    }

    public function test_history_running_balance_matches_final_balance(): void
    {
        [$ha, $aId] = $this->actor('1000000000001');
        $this->postJson('/api/deposit', ['amount' => 100], $ha)->assertOk();
        $this->postJson('/api/withdraw', ['amount' => 30, 'pin' => '123456'], $ha)->assertOk();
        $this->postJson('/api/deposit', ['amount' => 5.5], $ha)->assertOk();
        $latest = $this->getJson('/api/transactionhis', $ha)->json('transactions.0');
        $this->assertEquals(BankAccount::find($aId)->balance, $latest['balance']);
        $this->assertEquals(75.5, $latest['balance']);
    }

    // ---------- Auth edge cases ----------
    public function test_register_validation_edge_cases(): void
    {
        $base = [
            'firstname' => 'A', 'lastname' => 'B', 'idcard' => '2000000000001', 'phone' => '0811111111',
            'email' => 'a@test.com', 'address' => 'x', 'gender' => 'male', 'pin' => '123456',
        ];
        $this->postJson('/api/register', array_merge($base, ['pin' => '12345']))->assertStatus(422);
        $this->postJson('/api/register', array_merge($base, ['pin' => '12345a']))->assertStatus(422);
        $this->postJson('/api/register', array_merge($base, ['gender' => 'robot']))->assertStatus(422);
        $this->postJson('/api/register', array_merge($base, ['idcard' => '20000000000AB']))->assertStatus(422);
        $this->postJson('/api/register', array_merge($base, ['email' => 'not-an-email']))->assertStatus(422);
        $this->postJson('/api/register', array_merge($base, ['phone' => '12']))->assertStatus(422);
        $this->postJson('/api/register', [])->assertStatus(422);
        $this->assertSame(0, BankAccount::count());
    }

    public function test_register_duplicate_phone(): void
    {
        $this->actor('3000000000001');
        $this->postJson('/api/register', [
            'firstname' => 'A', 'lastname' => 'B', 'idcard' => '3000000000002', 'phone' => '08' . '00000001',
            'email' => 'other@test.com', 'address' => 'x', 'gender' => 'male', 'pin' => '123456',
        ])->assertStatus(422);
    }

    public function test_register_email_uniqueness_is_case_insensitive(): void
    {
        $this->actor('3000000000001'); // email u3000000000001@test.com
        $this->postJson('/api/register', [
            'firstname' => 'A', 'lastname' => 'B', 'idcard' => '3000000000002', 'phone' => '0899999999',
            'email' => 'U3000000000001@TEST.com', 'address' => 'x', 'gender' => 'male', 'pin' => '123456',
        ])->assertStatus(422);
    }

    public function test_register_creates_exactly_one_zero_balance_account(): void
    {
        $this->actor('3000000000001');
        $this->assertSame(1, BankAccount::where('user_id', '3000000000001')->count());
        $this->assertEquals(0, BankAccount::first()->balance);
    }

    public function test_login_validation_and_pin_not_stored_plain(): void
    {
        $this->actor('3000000000001');
        $this->postJson('/api/login', [])->assertStatus(422);
        $this->postJson('/api/login', ['userid' => '3000000000001', 'pin' => 'abcd'])->assertStatus(422);
        $this->postJson('/api/login', ['userid' => '123', 'pin' => '123456'])->assertStatus(422);
        $this->assertNotSame('123456', \App\Models\User::first()->pin);
    }

    public function test_malformed_and_logged_out_tokens_rejected_everywhere(): void
    {
        $bad = ['Authorization' => 'Bearer not.a.token'];
        foreach (['/api/user', '/api/bal', '/api/balance', '/api/transactionhis', '/api/saved-accounts'] as $url) {
            $this->getJson($url, $bad)->assertStatus(401);
        }
        $this->postJson('/api/deposit', ['amount' => 10], $bad)->assertStatus(401);
        $this->postJson('/api/logout', [], $bad)->assertStatus(401);

        [$ha] = $this->actor('3000000000001');
        $this->postJson('/api/logout', [], $ha)->assertOk();
        $this->postJson('/api/logout', [], $ha)->assertStatus(401);
        $this->getJson('/api/bal', $ha)->assertStatus(401);
    }

    public function test_user_endpoint_returns_profile(): void
    {
        [$ha] = $this->actor('3000000000001');
        $this->getJson('/api/user', $ha)->assertOk()
            ->assertJsonPath('userid', '3000000000001')
            ->assertJsonMissingPath('pin');
    }

    public function test_api_routes_are_not_duplicated_without_prefix(): void
    {
        // บัคเก่า: web.php require api.php ทำให้มี /login, /deposit ซ้ำ
        $this->postJson('/login', ['userid' => '3000000000001', 'pin' => '123456'])->assertStatus(404);
        $this->postJson('/deposit', ['amount' => 1])->assertStatus(404);
    }

    // ---------- Web pages ----------
    public function test_web_pages_render(): void
    {
        foreach (['/', '/about', '/Ulogin', '/Uregister', '/dashboard', '/Tran'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/no-such-page')->assertNotFound();
    }
}
