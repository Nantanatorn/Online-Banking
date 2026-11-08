<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** ล้าง guard/JWT ที่ cache ไว้ ให้แต่ละ request ยืนยันตัวตนจาก token ของตัวเอง */
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
            'firstname' => 'First' . substr($id, -2), 'lastname' => 'Lastname', 'idcard' => $id,
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

    public function test_deposit_and_withdraw_return_transaction_id_and_receipt(): void
    {
        [$h, $a] = $this->actor('1111111111111', 1000);

        $dep = $this->postJson('/api/deposit', ['amount' => 250], $h)->assertOk();
        $this->assertNotEmpty($dep->json('transaction_id'));

        $r = $this->getJson('/api/transactions/' . $dep->json('transaction_id') . '/receipt', $h)->assertOk();
        $this->assertSame('deposit', $r->json('type'));
        $this->assertSame('250.00', $r->json('amount'));
        $this->assertSame('1250.00', $r->json('balance'));
        $this->assertSame($a, $r->json('account.account_id'));
        $this->assertNull($r->json('counterparty'));
        $this->assertSame(12, strlen($r->json('reference')));

        $wd = $this->postJson('/api/withdraw', ['pin' => '123456', 'amount' => 50], $h)->assertOk();
        $r2 = $this->getJson('/api/transactions/' . $wd->json('transaction_id') . '/receipt', $h)->assertOk();
        $this->assertSame('withdraw', $r2->json('type'));
        $this->assertSame('1200.00', $r2->json('balance'));
    }

    public function test_transfer_receipt_for_sender_and_receiver(): void
    {
        [$h1, $a1] = $this->actor('1111111111111', 1000);
        [$h2, $a2] = $this->actor('2222222222222');

        $t = $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 100, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertOk();
        $r = $this->getJson('/api/transactions/' . $t->json('transaction_id') . '/receipt', $h1)->assertOk();

        $this->assertSame('transfer', $r->json('type'));
        $this->assertSame($a2, $r->json('counterparty.account_id'));
        // ชื่ออีกฝั่งต้องย่อนามสกุล ไม่โชว์เต็ม
        $this->assertSame('First22 L.', $r->json('counterparty.holder_name'));
        $this->assertSame('900.00', $r->json('balance'));

        // ฝั่งผู้รับ: สลิปเป็น type receive ของ user 2 (ดึง id จากตาราง)
        $recvId = \App\Models\Transaction::where('type', 'receive')->value('id');
        $r2 = $this->getJson('/api/transactions/' . $recvId . '/receipt', $h2)->assertOk();
        $this->assertSame('receive', $r2->json('type'));
        $this->assertSame($a1, $r2->json('counterparty.account_id'));
        $this->assertSame('First11 L.', $r2->json('counterparty.holder_name'));
    }

    public function test_cannot_view_someone_elses_receipt(): void
    {
        [$h1] = $this->actor('1111111111111');
        [$h2] = $this->actor('2222222222222');

        $dep = $this->postJson('/api/deposit', ['amount' => 100], $h1)->assertOk();
        $id = $dep->json('transaction_id');

        $this->getJson("/api/transactions/{$id}/receipt", $h2)->assertStatus(404);
        $this->getJson('/api/transactions/does-not-exist/receipt', $h1)->assertStatus(404);
        $this->getJson("/api/transactions/{$id}/receipt")->assertStatus(401);
    }
}
