<?php

namespace Tests\Feature;

use App\Models\GroceryWeek;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MovePaidSharesToPaymentsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_shares_become_payments_so_balances_carry_over(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create(['week_date' => '2026-06-01']);
        DB::table('grocery_shares')->insert([
            ['grocery_week_id' => $week->id, 'sister_id' => $sister->id, 'amount' => 50.00, 'is_paid' => true, 'paid_at' => '2026-06-04 10:00:00'],
            ['grocery_week_id' => $week->id, 'sister_id' => $sister->id, 'amount' => 30.00, 'is_paid' => false, 'paid_at' => null],
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('grocery_shares', 'is_paid'));
        $this->assertDatabaseCount('payments', 1);
        $payment = $sister->payments()->first();
        $this->assertEquals(50.00, $payment->amount);
        $this->assertSame('2026-06-04', $payment->paid_on->toDateString());
        $this->assertSame('Week of 2026-06-01', $payment->notes);
        $this->assertSame(30.00, $sister->outstandingTotal());
    }
}
