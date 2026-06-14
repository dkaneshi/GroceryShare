<?php

namespace Tests\Unit;

use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_total_sums_unpaid_shares(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 30.00]);

        $this->assertEquals(80.00, $sister->outstandingTotal());
    }

    public function test_outstanding_total_excludes_paid_shares(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);
        GroceryShare::factory()->paid()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 30.00]);

        $this->assertEquals(50.00, $sister->outstandingTotal());
    }

    public function test_outstanding_total_returns_zero_when_all_paid(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->paid()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);

        $this->assertEquals(0.0, $sister->outstandingTotal());
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
