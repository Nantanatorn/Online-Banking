<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private array $h;
    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postJson('/api/register', [
            'firstname' => 'Somchai', 'lastname' => 'Jaidee', 'idcard' => '1111111111111',
            'phone' => '0811111111', 'email' => 'u1@test.com',
            'address' => 'Bangkok', 'gender' => 'male', 'pin' => '123456',
        ])->assertStatus(201);
        $token = $this->postJson('/api/login', ['userid' => '1111111111111', 'pin' => '123456'])->json('token');
        $this->h = ['Authorization' => "Bearer {$token}"];
        $this->acc = BankAccount::where('user_id', '1111111111111')->value('account_id');
    }

    private function addTx(string $type, float $amount, string $when): void
    {
        $t = Transaction::create([
            'id' => (string) Str::uuid(), 'account_id' => $this->acc, 'type' => $type,
            'amount' => $amount, 'balance' => 0, 'to_account_id' => null,
        ]);
        DB::table('transactions')->where('id', $t->id)->update(['created_at' => $when]);
    }

    private function data(): void
    {
        $this->addTx('deposit', 100, '2026-11-01 10:00:00');
        $this->addTx('deposit', 200, '2026-11-05 23:59:30');
        $this->addTx('withdraw', 50, '2026-11-05 08:00:00');
        $this->addTx('transfer', 30, '2026-11-07 12:00:00');
        $this->addTx('receive', 70, '2026-11-09 09:00:00');
    }

    public function test_filter_by_type(): void
    {
        $this->data();
        $r = $this->getJson('/api/transactionhis?type=deposit', $this->h)->assertOk();

        $this->assertSame(2, $r->json('pagination.total'));
        $this->assertCount(2, $r->json('transactions'));
        foreach ($r->json('transactions') as $t) {
            $this->assertSame('deposit', $t['type']);
        }
    }

    public function test_filter_by_date_range_is_inclusive_of_whole_days(): void
    {
        $this->data();
        // 5 พ.ย. ทั้งวัน รวมรายการ 23:59:30
        $r = $this->getJson('/api/transactionhis?from=2026-11-05&to=2026-11-05', $this->h)->assertOk();
        $this->assertSame(2, $r->json('pagination.total'));

        $r = $this->getJson('/api/transactionhis?from=2026-11-06', $this->h)->assertOk();
        $this->assertSame(2, $r->json('pagination.total')); // 7 และ 9 พ.ย.

        $r = $this->getJson('/api/transactionhis?to=2026-11-01', $this->h)->assertOk();
        $this->assertSame(1, $r->json('pagination.total'));
    }

    public function test_combined_filters_and_totals_cover_whole_filtered_set(): void
    {
        $this->data();
        $r = $this->getJson('/api/transactionhis?type=deposit&from=2026-11-01&to=2026-11-30&per_page=5', $this->h)->assertOk();

        $this->assertSame(2, $r->json('pagination.total'));
        $this->assertSame('300.00', $r->json('totals.deposit'));
        $this->assertSame('0.00', $r->json('totals.withdraw'));
    }

    public function test_pagination_pages_and_totals_ignore_page_size(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->addTx('deposit', 10, '2026-11-02 10:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00');
        }

        $p1 = $this->getJson('/api/transactionhis?per_page=5&page=1', $this->h)->assertOk();
        $p3 = $this->getJson('/api/transactionhis?per_page=5&page=3', $this->h)->assertOk();

        $this->assertSame(12, $p1->json('pagination.total'));
        $this->assertSame(3, $p1->json('pagination.last_page'));
        $this->assertCount(5, $p1->json('transactions'));
        $this->assertCount(2, $p3->json('transactions'));
        // ยอดรวมต้องเป็นของทั้ง 12 รายการ ไม่ใช่เฉพาะหน้านั้น
        $this->assertSame('120.00', $p1->json('totals.deposit'));
        $this->assertSame('120.00', $p3->json('totals.deposit'));
    }

    public function test_results_are_newest_first(): void
    {
        $this->data();
        $dates = array_column($this->getJson('/api/transactionhis', $this->h)->json('transactions'), 'created_at');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/transactionhis?type=bogus', $this->h)->assertStatus(422);
        $this->getJson('/api/transactionhis?from=not-a-date', $this->h)->assertStatus(422);
        $this->getJson('/api/transactionhis?from=2026-11-09&to=2026-11-01', $this->h)->assertStatus(422);
        $this->getJson('/api/transactionhis?per_page=1000', $this->h)->assertStatus(422);
        $this->getJson('/api/transactionhis?page=0', $this->h)->assertStatus(422);
    }

    public function test_no_filters_still_works_for_empty_history(): void
    {
        $r = $this->getJson('/api/transactionhis', $this->h)->assertOk();
        $this->assertSame(0, $r->json('pagination.total'));
        $this->assertSame([], $r->json('transactions'));
        $this->assertSame('0.00', $r->json('totals.deposit'));
    }
}
