<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferLimitTest extends TestCase
{
    use RefreshDatabase;

    /** ล้าง guard/JWT ที่ cache user ไว้ ให้แต่ละ request โหลด user สดจาก DB (เหมือนตอนใช้งานจริง) */
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
            'firstname' => 'Somchai', 'lastname' => 'Jaidee', 'idcard' => $id,
            'phone' => '08' . substr($id, 0, 8), 'email' => "u{$id}@test.com",
            'address' => 'Bangkok', 'gender' => 'male', 'pin' => '123456',
        ])->assertStatus(201);
        $token = $this->postJson('/api/login', ['userid' => $id, 'pin' => '123456'])->json('token');
        $acc = BankAccount::where('user_id', $id)->first();
        if ($balance) {
            $acc->update(['balance' => $balance]);
        }
        return [['Authorization' => "Bearer {$token}"], $acc->account_id];
    }

    private function transfer(array $h, int $from, int $to, float $amount)
    {
        return $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => $amount, 'source_account_id' => $from, 'target_account_id' => $to], $h);
    }

    public function test_single_transfer_over_daily_limit_is_rejected(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 2000000);
        [, $a2] = $this->actor('2222222222222');

        $this->transfer($h1, $a1, $a2, 500000.01)->assertStatus(400);
        $this->assertSame('2000000.00', BankAccount::find($a1)->balance);
    }

    public function test_daily_limit_accumulates_across_transfers(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 2000000);
        [, $a2] = $this->actor('2222222222222');

        $this->transfer($h1, $a1, $a2, 300000)->assertOk();
        $this->transfer($h1, $a1, $a2, 300000)->assertStatus(400)->assertJsonPath('remaining', '200000.00');
        $this->transfer($h1, $a1, $a2, 200000)->assertOk(); // ครบ 500,000 พอดี
        $this->transfer($h1, $a1, $a2, 1)->assertStatus(400);
        $this->assertSame('1500000.00', BankAccount::find($a1)->balance);
    }

    public function test_limit_resets_next_day(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 2000000);
        [, $a2] = $this->actor('2222222222222');

        $this->transfer($h1, $a1, $a2, 500000)->assertOk();
        $this->transfer($h1, $a1, $a2, 1)->assertStatus(400);

        // ย้ายธุรกรรมที่โอนไปให้เป็นของเมื่อวาน -> วงเงินวันนี้ต้องกลับมาเต็ม
        \Illuminate\Support\Facades\DB::table('transactions')->where('type', 'transfer')->update(['created_at' => now()->subDay()->startOfDay()->addHours(12)]);

        $this->transfer($h1, $a1, $a2, 500000)->assertOk();
    }

    public function test_deposit_and_receive_do_not_count_toward_limit(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 2000000);
        [, $a2] = $this->actor('2222222222222');

        $this->postJson('/api/deposit', ['amount' => 900000], $h1)->assertOk();
        $this->transfer($h1, $a1, $a2, 500000)->assertOk();
    }

    public function test_limit_endpoint_reports_usage(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 2000000);
        [, $a2] = $this->actor('2222222222222');
        $this->transfer($h1, $a1, $a2, 120000)->assertOk();

        $this->getJson('/api/transfer/limit', $h1)->assertOk()
            ->assertJsonPath('limit', '500000.00')
            ->assertJsonPath('used', '120000.00')
            ->assertJsonPath('remaining', '380000.00');
    }

    public function test_reset_command_restores_limit_without_deleting_history(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 2000000);
        [, $a2] = $this->actor('2222222222222');

        $this->transfer($h1, $a1, $a2, 500000)->assertOk();
        $this->transfer($h1, $a1, $a2, 1)->assertStatus(400);

        $this->travel(2)->seconds(); // ให้ธุรกรรมเก่าอยู่ก่อนเวลารีเซตแน่ๆ
        $this->artisan('limit:reset')->assertSuccessful();

        $this->getJson('/api/transfer/limit', $h1)->assertOk()->assertJsonPath('remaining', '500000.00');
        $this->transfer($h1, $a1, $a2, 500000)->assertOk();
        $this->transfer($h1, $a1, $a2, 1)->assertStatus(400); // วงเงินใหม่ก็ใช้หมดได้เหมือนเดิม

        // ประวัติไม่ถูกลบ: transfer 2 รายการของผู้โอน
        $this->assertSame(2, \App\Models\Transaction::where('type', 'transfer')->count());
    }
}
