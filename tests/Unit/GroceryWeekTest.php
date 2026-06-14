<?php

namespace Tests\Unit;

use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroceryWeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_paid_returns_true_when_all_shares_are_paid(): void
    {
        $week = GroceryWeek::factory()->create();
        $sister1 = Sister::factory()->create();
        $sister2 = Sister::factory()->create();
        GroceryShare::factory()->paid()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister1->id]);
        GroceryShare::factory()->paid()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister2->id]);

        $this->assertTrue($week->allPaid());
    }

    public function test_all_paid_returns_false_when_any_share_is_unpaid(): void
    {
        $week = GroceryWeek::factory()->create();
        $sister1 = Sister::factory()->create();
        $sister2 = Sister::factory()->create();
        GroceryShare::factory()->paid()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister1->id]);
        GroceryShare::factory()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister2->id, 'is_paid' => false]);

        $this->assertFalse($week->allPaid());
    }

    public function test_all_paid_returns_true_when_no_shares_exist(): void
    {
        $week = GroceryWeek::factory()->create();

        $this->assertTrue($week->allPaid());
    }

    public function test_shares_relationship_returns_has_many_grocery_shares(): void
    {
        $week = GroceryWeek::factory()->create();
        $sister = Sister::factory()->create();
        GroceryShare::factory()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister->id]);

        $this->assertCount(1, $week->shares);
        $this->assertInstanceOf(GroceryShare::class, $week->shares->first());
    }
}
