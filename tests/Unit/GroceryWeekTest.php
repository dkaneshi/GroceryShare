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

    public function test_shares_relationship_returns_has_many_grocery_shares(): void
    {
        $week = GroceryWeek::factory()->create();
        $sister = Sister::factory()->create();
        GroceryShare::factory()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister->id]);

        $this->assertCount(1, $week->shares);
        $this->assertInstanceOf(GroceryShare::class, $week->shares->first());
    }
}
