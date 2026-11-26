<?php

namespace Tests\Feature;

use App\Mail\ShareNotification;
use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Payment;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_has_correct_subject_with_week_date(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create(['week_date' => '2026-06-01']);
        $mailable = new ShareNotification($sister, $week, 50.00);

        $mailable->assertHasSubject('Your grocery share for the week of June 1, 2026');
    }

    public function test_mail_renders_share_notification_template(): void
    {
        $sister = Sister::factory()->create(['name' => 'Jane Doe']);
        $week = GroceryWeek::factory()->create(['week_date' => '2026-06-01']);
        $mailable = new ShareNotification($sister, $week, 50.00);

        $mailable->assertSeeInHtml($sister->name);
    }

    public function test_mail_exposes_sister_week_and_amount_to_view(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        $mailable = new ShareNotification($sister, $week, 75.00);

        $this->assertEquals($sister->id, $mailable->sister->id);
        $this->assertEquals($week->id, $mailable->week->id);
        $this->assertEquals(75.00, $mailable->amount);
    }

    public function test_mail_shows_current_total_balance(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'amount' => 50.00]);
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 40.00]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 25.00]);
        $mailable = new ShareNotification($sister, $week, 40.00);

        $mailable->assertSeeInHtml('Your current total balance: $65.00');
        $mailable->assertSeeInText('Please send **$65.00** when you can.');
    }

    public function test_mail_shows_remaining_credit_when_overpaid(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 40.00]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 50.00]);
        $mailable = new ShareNotification($sister, $week, 40.00);

        $mailable->assertSeeInHtml('$10.00</strong> in credit', false);
        $mailable->assertDontSeeInHtml('Please send');
    }
}
