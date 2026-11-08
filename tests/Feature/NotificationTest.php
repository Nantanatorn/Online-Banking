<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $id, float $balance = 0): array
    {
        $this->postJson('/api/register', [
            'firstname' => 'Somchai', 'lastname' => 'Jaidee', 'idcard' => $id,
            'phone' => '08' . substr($id, 0, 8), 'email' => "u{$id}@test.com",
            'address' => 'Bangkok', 'gender' => 'male', 'pin' => '123456',
        ])->assertStatus(201);
        $token = $this->postJson('/api/login', ['userid' => $id, 'pin' => '123456'])->json('token');
        $acc = \App\Models\BankAccount::where('user_id', $id)->first();
        if ($balance) {
            $acc->update(['balance' => $balance]);
        }
        return [['Authorization' => "Bearer {$token}"], $acc->account_id];
    }

    /** ล้าง guard/JWT ที่ cache ไว้ ให้แต่ละ request ยืนยันตัวตนจาก token ของตัวเอง (เหมือน BankingExtendedTest) */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app) {
                foreach (['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'] as $k) {
                $this->app->forgetInstance($k);
            }
        }
        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function test_deposit_creates_notification_and_email(): void
    {
        Mail::fake();
        [$h] = $this->actor('1111111111111');

        $this->postJson('/api/deposit', ['amount' => 100], $h)->assertOk();

        $this->assertSame(1, AppNotification::where('user_id', '1111111111111')->count());
        Mail::assertSentCount(1);
        $r = $this->getJson('/api/notifications', $h)->assertOk();
        $this->assertSame(1, $r->json('unread_count'));
        $this->assertSame('deposit', $r->json('notifications.0.type'));
    }

    public function test_transfer_notifies_both_sides_only(): void
    {
        Mail::fake();
        [$h1, $a1] = $this->actor('1111111111111', 500);
        [$h2, $a2] = $this->actor('2222222222222');

        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 100, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertOk();

        $this->assertSame('transfer', $this->getJson('/api/notifications', $h1)->json('notifications.0.type'));
        // ผู้รับ: เปลี่ยนไปเป็น user 2 ด้วย actingAs (วิธีเดียวกับ BankingApiTest เพราะ guard cache user คนแรกไว้)
        $this->actingAs(\App\Models\User::find('2222222222222'), 'api');
        $r2 = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame('receive', $r2->json('notifications.0.type'));
        $this->assertCount(1, $r2->json('notifications'));
        Mail::assertSentCount(2);
    }

    public function test_failed_transfer_creates_no_notification(): void
    {
        Mail::fake();
        [$h1, $a1] = $this->actor('1111111111111', 50);
        [, $a2] = $this->actor('2222222222222');

        $this->postJson('/api/transfer', ['pin' => '123456', 'amount' => 100, 'source_account_id' => $a1, 'target_account_id' => $a2], $h1)->assertStatus(400);

        $this->assertSame(0, AppNotification::count());
        Mail::assertNothingSent();
    }

    public function test_mark_all_read_and_requires_auth(): void
    {
        [$h] = $this->actor('1111111111111');
        $this->postJson('/api/deposit', ['amount' => 10], $h)->assertOk();

        $this->postJson('/api/notifications/read', [], $h)->assertOk();
        $this->assertSame(0, $this->getJson('/api/notifications', $h)->json('unread_count'));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}
