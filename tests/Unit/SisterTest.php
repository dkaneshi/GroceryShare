<?php

namespace Tests\Unit;

use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Payment;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_total_sums_all_shares_when_nothing_paid(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 30.00]);

        $this->assertEquals(80.00, $sister->outstandingTotal());
    }

    public function test_outstanding_total_subtracts_partial_payments(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 30.00]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 20.10]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 9.80]);

        $this->assertSame(50.10, $sister->outstandingTotal());
    }

    public function test_outstanding_total_returns_zero_when_payments_match_shares(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 50.00]);

        $this->assertEquals(0.0, $sister->outstandingTotal());
    }

    public function test_outstanding_total_is_negative_credit_after_overpayment(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 70.00]);

        $this->assertEquals(-20.00, $sister->outstandingTotal());
    }

    public function test_outstanding_total_ignores_other_sisters_payments(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        Payment::factory()->create(['amount' => 50.00]);

        $this->assertEquals(50.00, $sister->outstandingTotal());
    }

    public function test_outstanding_total_returns_zero_with_no_shares(): void
    {
        $sister = Sister::factory()->create();

        $this->assertEquals(0.0, $sister->outstandingTotal());
    }

    public function test_shares_relationship_returns_has_many_grocery_shares(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id]);
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id]);

        $this->assertCount(2, $sister->shares);
        $this->assertInstanceOf(GroceryShare::class, $sister->shares->first());
    }
}
