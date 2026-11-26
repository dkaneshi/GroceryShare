<?php

namespace Tests\Feature;

use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Payment;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $exportDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportDirectory = storage_path('framework/testing/imports');
        File::deleteDirectory($this->exportDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportDirectory);

        parent::tearDown();
    }

    public function test_export_can_be_imported_into_an_empty_database(): void
    {
        $sister = Sister::factory()->create(['name' => 'Jane']);
        $week = GroceryWeek::factory()->create(['notes' => 'Costco "bulk" run, eggs']);
        $share = GroceryShare::factory()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id]);
        Payment::factory()->create(['sister_id' => $sister->id, 'amount' => 12.50, 'notes' => 'Venmo']);
        $original = [Sister::all()->toArray(), GroceryWeek::all()->toArray(), GroceryShare::all()->toArray(), Payment::all()->toArray()];
        $file = $this->export();

        Payment::query()->delete();
        GroceryShare::query()->delete();
        GroceryWeek::query()->delete();
        Sister::query()->delete();

        $this->artisan('app:import-data', ['file' => $file])
            ->expectsOutputToContain('Imported from:')
            ->assertSuccessful();

        $this->assertSame($original, [Sister::all()->toArray(), GroceryWeek::all()->toArray(), GroceryShare::all()->toArray(), Payment::all()->toArray()]);
        $this->assertSame($sister->id, $share->fresh()->sister->id);
    }

    public function test_import_refuses_to_overwrite_existing_data_without_force(): void
    {
        Sister::factory()->create(['name' => 'Exported']);
        $file = $this->export();
        Sister::factory()->create(['name' => 'Added later']);

        $this->artisan('app:import-data', ['file' => $file])
            ->expectsOutputToContain('Re-run with --force')
            ->assertFailed();

        $this->assertSame(2, Sister::count());
    }

    public function test_import_with_force_replaces_existing_data(): void
    {
        Sister::factory()->create(['name' => 'Exported']);
        $file = $this->export();
        $later = Sister::factory()->create(['name' => 'Added later']);
        GroceryShare::factory()->create(['sister_id' => $later->id]);

        $this->artisan('app:import-data', ['file' => $file, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(['Exported'], Sister::pluck('name')->all());
        $this->assertSame(0, GroceryShare::count());
        $this->assertSame(0, GroceryWeek::count());
    }

    public function test_failed_insert_rolls_back_and_keeps_existing_data(): void
    {
        $existing = Sister::factory()->create(['name' => 'Existing']);
        $week = GroceryWeek::factory()->create();
        File::ensureDirectoryExists($this->exportDirectory);
        $file = $this->exportDirectory.'/orphan.json';
        File::put($file, json_encode(['tables' => [
            'sisters' => ['rows' => []],
            'grocery_weeks' => ['rows' => [$week->getAttributes()]],
            'grocery_shares' => ['rows' => [GroceryShare::factory()->make(['id' => 1, 'sister_id' => 999, 'grocery_week_id' => $week->id])->getAttributes()]],
            'payments' => ['rows' => []],
        ]]));

        $this->artisan('app:import-data', ['file' => $file, '--force' => true])
            ->expectsOutputToContain('Import failed, nothing was changed')
            ->assertFailed();

        $this->assertSame(['Existing'], Sister::pluck('name')->all());
        $this->assertTrue($existing->is(Sister::first()));
        $this->assertSame(0, GroceryShare::count());
    }

    public function test_import_fails_for_missing_file(): void
    {
        $this->artisan('app:import-data', ['file' => '/nope/missing.json'])
            ->expectsOutputToContain('File not found')
            ->assertFailed();
    }

    public function test_import_fails_for_invalid_json_or_missing_tables_without_touching_data(): void
    {
        Sister::factory()->create();
        File::ensureDirectoryExists($this->exportDirectory);

        File::put($this->exportDirectory.'/bad.json', '{not json');
        $this->artisan('app:import-data', ['file' => $this->exportDirectory.'/bad.json', '--force' => true])
            ->expectsOutputToContain('Invalid JSON')
            ->assertFailed();

        File::put($this->exportDirectory.'/partial.json', json_encode(['tables' => ['sisters' => ['rows' => []]]]));
        $this->artisan('app:import-data', ['file' => $this->exportDirectory.'/partial.json', '--force' => true])
            ->expectsOutputToContain('missing the [grocery_weeks] table')
            ->assertFailed();

        $this->assertSame(1, Sister::count());
    }

    private function export(): string
    {
        $this->artisan('app:export-data', ['--path' => $this->exportDirectory])->assertSuccessful();

        return File::glob($this->exportDirectory.'/*.json')[0];
    }
}
