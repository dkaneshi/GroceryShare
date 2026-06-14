<?php

namespace Tests\Feature;

use App\Mail\ShareNotification;
use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Sister;
use App\Services\TwilioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_defaults_week_date_to_most_recent_sunday(): void
    {
        $component = Livewire::test('dashboard');

        $weekDate = $component->get('weekDate');
        $this->assertNotEmpty($weekDate);
        $this->assertEquals(0, Carbon::parse($weekDate)->dayOfWeek);
    }

    public function test_add_week_creates_grocery_week_and_shares_for_all_sisters(): void
    {
        Mail::fake();
        $this->mock(TwilioService::class)->shouldReceive('sendSms')->andReturnNull();
        Sister::factory()->count(3)->create();

        Livewire::test('dashboard')
            ->set('totalAmount', '150.00')
            ->set('weekDate', '2026-06-01')
            ->call('addWeek')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('grocery_weeks', 1);
        $this->assertDatabaseCount('grocery_shares', 3);
        $this->assertDatabaseHas('grocery_weeks', ['total_amount' => 150.00, 'share_amount' => 50]);
    }

    public function test_add_week_calculates_share_as_floor_of_total_divided_by_three(): void
    {
        Mail::fake();
        $this->mock(TwilioService::class)->shouldReceive('sendSms')->andReturnNull();
        Sister::factory()->count(3)->create();

        Livewire::test('dashboard')
            ->set('totalAmount', '100.00')
            ->set('weekDate', '2026-06-01')
            ->call('addWeek');

        $this->assertDatabaseHas('grocery_weeks', ['share_amount' => 33]);
        $this->assertDatabaseHas('grocery_shares', ['amount' => 33.00]);
    }

    public function test_add_week_sends_email_to_each_sister(): void
    {
        Mail::fake();
        $this->mock(TwilioService::class)->shouldReceive('sendSms')->andReturnNull();
        $sisters = Sister::factory()->count(3)->create();

        Livewire::test('dashboard')
            ->set('totalAmount', '150.00')
            ->set('weekDate', '2026-06-01')
            ->call('addWeek');

        Mail::assertSentCount(3);
        foreach ($sisters as $sister) {
            Mail::assertSent(ShareNotification::class, fn ($mail) => $mail->hasTo($sister->email));
        }
    }

    public function test_add_week_sends_sms_only_to_sisters_with_phone(): void
    {
        Mail::fake();
        $twilioMock = $this->mock(TwilioService::class);
        $twilioMock->shouldReceive('sendSms')->times(2);
        Sister::factory()->withPhone()->count(2)->create();
        Sister::factory()->create(['phone' => null]);

        Livewire::test('dashboard')
            ->set('totalAmount', '150.00')
            ->set('weekDate', '2026-06-01')
            ->call('addWeek');
    }

    public function test_add_week_fails_validation_without_total_amount(): void
    {
        Sister::factory()->count(3)->create();

        Livewire::test('dashboard')
            ->set('weekDate', '2026-06-01')
            ->call('addWeek')
            ->assertHasErrors(['totalAmount' => 'required']);
    }

    public function test_add_week_fails_validation_without_week_date(): void
    {
        Sister::factory()->count(3)->create();

        Livewire::test('dashboard')
            ->set('totalAmount', '150.00')
            ->set('weekDate', '')
            ->call('addWeek')
            ->assertHasErrors(['weekDate' => 'required']);
    }

    public function test_add_week_requires_at_least_two_sisters(): void
    {
        Sister::factory()->create();

        Livewire::test('dashboard')
            ->set('totalAmount', '150.00')
            ->set('weekDate', '2026-06-01')
            ->call('addWeek')
            ->assertHasErrors('sisters');

        $this->assertDatabaseCount('grocery_weeks', 0);
    }

    public function test_add_week_flashes_success_and_resets_form(): void
    {
        Mail::fake();
        $this->mock(TwilioService::class)->shouldReceive('sendSms')->andReturnNull();
        Sister::factory()->count(3)->create();

        Livewire::test('dashboard')
            ->set('totalAmount', '150.00')
            ->set('weekDate', '2026-06-01')
            ->set('notes', 'Weekly shop')
            ->call('addWeek')
            ->assertSet('showAddForm', false)
            ->assertSet('totalAmount', '')
            ->assertSet('notes', '');

        $this->assertDatabaseCount('grocery_weeks', 1);
    }

    public function test_mark_paid_sets_share_as_paid_with_timestamp(): void
    {
        $share = GroceryShare::factory()->create(['is_paid' => false]);

        Livewire::test('dashboard')->call('markPaid', $share->id);

        $this->assertDatabaseHas('grocery_shares', ['id' => $share->id, 'is_paid' => true]);
        $this->assertNotNull($share->fresh()->paid_at);
    }

    public function test_mark_unpaid_clears_paid_status_and_timestamp(): void
    {
        $share = GroceryShare::factory()->paid()->create();

        Livewire::test('dashboard')->call('markUnpaid', $share->id);

        $this->assertDatabaseHas('grocery_shares', ['id' => $share->id, 'is_paid' => false]);
        $this->assertNull($share->fresh()->paid_at);
    }

    public function test_open_edit_week_loads_week_data_into_form(): void
    {
        $week = GroceryWeek::factory()->create([
            'total_amount' => 150.00,
            'week_date' => '2026-06-01',
            'notes' => 'Test notes',
        ]);

        Livewire::test('dashboard')
            ->call('openEditWeek', $week->id)
            ->assertSet('editingWeekId', $week->id)
            ->assertSet('editWeekDate', '2026-06-01')
            ->assertSet('editNotes', 'Test notes')
            ->assertSet('showEditForm', true);
    }

    public function test_update_week_persists_changes_to_week_and_shares(): void
    {
        $week = GroceryWeek::factory()->create(['total_amount' => 150.00, 'share_amount' => 50.00]);
        $sister = Sister::factory()->create();
        GroceryShare::factory()->create(['grocery_week_id' => $week->id, 'sister_id' => $sister->id, 'amount' => 50.00]);

        Livewire::test('dashboard')
            ->set('editingWeekId', $week->id)
            ->set('editTotalAmount', '180.00')
            ->set('editWeekDate', '2026-06-08')
            ->set('editNotes', 'Updated notes')
            ->call('updateWeek')
            ->assertHasNoErrors()
            ->assertSet('showEditForm', false)
            ->assertSet('editingWeekId', null);

        $this->assertDatabaseHas('grocery_weeks', ['id' => $week->id, 'total_amount' => 180.00, 'share_amount' => 60, 'notes' => 'Updated notes']);
        $this->assertDatabaseHas('grocery_shares', ['grocery_week_id' => $week->id, 'amount' => 60.00]);
    }

    public function test_update_week_fails_validation_without_required_fields(): void
    {
        $week = GroceryWeek::factory()->create();

        Livewire::test('dashboard')
            ->set('editingWeekId', $week->id)
            ->call('updateWeek')
            ->assertHasErrors(['editTotalAmount', 'editWeekDate']);
    }

    public function test_save_sister_creates_new_sister(): void
    {
        Livewire::test('dashboard')
            ->set('sisterName', 'Jane Doe')
            ->set('sisterEmail', 'jane@example.com')
            ->set('sisterPhone', '+15551234567')
            ->call('saveSister')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sisters', ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+15551234567']);
    }

    public function test_save_sister_updates_existing_sister(): void
    {
        $sister = Sister::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        Livewire::test('dashboard')
            ->set('editingSisterId', $sister->id)
            ->set('sisterName', 'New Name')
            ->set('sisterEmail', 'new@example.com')
            ->call('saveSister')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sisters', ['id' => $sister->id, 'name' => 'New Name', 'email' => 'new@example.com']);
    }

    public function test_save_sister_fails_validation_without_required_fields(): void
    {
        Livewire::test('dashboard')
            ->call('saveSister')
            ->assertHasErrors(['sisterName', 'sisterEmail']);
    }

    public function test_save_sister_fails_validation_with_invalid_email(): void
    {
        Livewire::test('dashboard')
            ->set('sisterName', 'Jane')
            ->set('sisterEmail', 'not-an-email')
            ->call('saveSister')
            ->assertHasErrors(['sisterEmail' => 'email']);
    }

    public function test_save_sister_resets_form_on_success(): void
    {
        Livewire::test('dashboard')
            ->set('sisterName', 'Jane Doe')
            ->set('sisterEmail', 'jane@example.com')
            ->call('saveSister')
            ->assertSet('showSisterForm', false)
            ->assertSet('sisterName', '')
            ->assertSet('sisterEmail', '')
            ->assertSet('sisterPhone', '');
    }

    public function test_edit_sister_loads_data_into_form(): void
    {
        $sister = Sister::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+15551234567']);

        Livewire::test('dashboard')
            ->call('editSister', $sister->id)
            ->assertSet('editingSisterId', $sister->id)
            ->assertSet('sisterName', 'Jane Doe')
            ->assertSet('sisterEmail', 'jane@example.com')
            ->assertSet('sisterPhone', '+15551234567')
            ->assertSet('showSisterForm', true);
    }

    public function test_delete_sister_removes_sister_from_database(): void
    {
        $sister = Sister::factory()->create();

        Livewire::test('dashboard')->call('deleteSister', $sister->id);

        $this->assertDatabaseMissing('sisters', ['id' => $sister->id]);
    }

    public function test_delete_sister_cascades_to_shares(): void
    {
        $sister = Sister::factory()->create();
        $week = GroceryWeek::factory()->create();
        GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id]);

        Livewire::test('dashboard')->call('deleteSister', $sister->id);

        $this->assertDatabaseMissing('grocery_shares', ['sister_id' => $sister->id]);
    }

    public function test_send_test_sms_sends_message_to_sister_with_phone(): void
    {
        $sister = Sister::factory()->withPhone()->create();
        $this->mock(TwilioService::class)
            ->shouldReceive('sendSms')
            ->once()
            ->with($sister->phone, \Mockery::type('string'));

        Livewire::test('dashboard')->call('sendTestSms', $sister->id);
    }

    public function test_send_test_sms_does_not_send_when_sister_has_no_phone(): void
    {
        $sister = Sister::factory()->create(['phone' => null]);
        $this->mock(TwilioService::class)->shouldNotReceive('sendSms');

        Livewire::test('dashboard')->call('sendTestSms', $sister->id);
    }

    public function test_cancel_sister_form_resets_all_form_state(): void
    {
        Livewire::test('dashboard')
            ->set('showSisterForm', true)
            ->set('editingSisterId', 5)
            ->set('sisterName', 'Jane')
            ->set('sisterEmail', 'jane@example.com')
            ->call('cancelSisterForm')
            ->assertSet('showSisterForm', false)
            ->assertSet('editingSisterId', null)
            ->assertSet('sisterName', '')
            ->assertSet('sisterEmail', '');
    }
}
