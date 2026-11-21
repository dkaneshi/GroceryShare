<?php

namespace Tests\Feature;

use App\Models\GroceryShare;
use App\Models\GroceryWeek;
use App\Models\Sister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $exportDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportDirectory = storage_path('framework/testing/exports');
        File::deleteDirectory($this->exportDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportDirectory);

        parent::tearDown();
    }

    public function test_json_export_contains_all_tables_and_rows(): void
    {
        $sister = Sister::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $week = GroceryWeek::factory()->create(['total_amount' => 150.00, 'notes' => 'Costco "bulk" run, eggs']);
        GroceryShare::factory()->paid()->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id, 'amount' => 50.00]);

        $this->artisan('app:export-data', ['--path' => $this->exportDirectory])
            ->expectsOutputToContain('Export written to:')
            ->assertSuccessful();

        $files = File::glob($this->exportDirectory.'/groceryshare-export-*.json');
        $this->assertCount(1, $files);

        $export = json_decode(File::get($files[0]), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['sisters' => 1, 'grocery_weeks' => 1, 'grocery_shares' => 1], $export['counts']);
        $this->assertArrayHasKey('exported_at', $export);
        $this->assertSame('Jane', $export['tables']['sisters']['rows'][0]['name']);
        $this->assertSame('Costco "bulk" run, eggs', $export['tables']['grocery_weeks']['rows'][0]['notes']);
        $this->assertSame($sister->id, (int) $export['tables']['grocery_shares']['rows'][0]['sister_id']);
        $this->assertSame($week->id, (int) $export['tables']['grocery_shares']['rows'][0]['grocery_week_id']);
        $this->assertContains('paid_at', $export['tables']['grocery_shares']['columns']);
    }

    public function test_csv_export_writes_one_file_per_table_with_headers(): void
    {
        $sister = Sister::factory()->create(['name' => 'Jane']);
        $week = GroceryWeek::factory()->create(['notes' => 'Costco "bulk" run, eggs']);
        GroceryShare::factory()->count(2)->create(['sister_id' => $sister->id, 'grocery_week_id' => $week->id]);

        $this->artisan('app:export-data', ['--format' => 'csv', '--path' => $this->exportDirectory])
            ->assertSuccessful();

        $folders = File::directories($this->exportDirectory);
        $this->assertCount(1, $folders);

        foreach (['sisters' => 1, 'grocery_weeks' => 1, 'grocery_shares' => 2] as $table => $expectedRows) {
            $path = $folders[0].'/'.$table.'.csv';
            $this->assertFileExists($path);

            $handle = fopen($path, 'r');
            $header = fgetcsv($handle, escape: '');
            $rows = [];
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $rows[] = array_combine($header, $row);
            }
            fclose($handle);

            $this->assertContains('id', $header);
            $this->assertCount($expectedRows, $rows, "Unexpected row count for {$table}.csv");

            if ($table === 'grocery_weeks') {
                $this->assertSame('Costco "bulk" run, eggs', $rows[0]['notes']);
            }
        }
    }

    public function test_export_succeeds_with_empty_database(): void
    {
        $this->artisan('app:export-data', ['--path' => $this->exportDirectory])
            ->assertSuccessful();

        $export = json_decode(File::get(File::glob($this->exportDirectory.'/*.json')[0]), true);

        $this->assertSame(['sisters' => 0, 'grocery_weeks' => 0, 'grocery_shares' => 0], $export['counts']);
        $this->assertNotEmpty($export['tables']['sisters']['columns']);
    }

    public function test_unsupported_format_fails_without_writing_files(): void
    {
        $this->artisan('app:export-data', ['--format' => 'xml', '--path' => $this->exportDirectory])
            ->expectsOutputToContain('Unsupported format [xml]')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->exportDirectory);
    }
}
