<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('app:export-data
    {--format=json : Export format: "json" (single file) or "csv" (one file per table)}
    {--path= : Directory to write the export to (defaults to storage/app/private/exports)}')]
#[Description('Export all GroceryShare data (sisters, weeks, shares) to portable JSON or CSV files')]
class ExportData extends Command
{
    /**
     * Tables to export, ordered so parents come before the rows that reference them.
     *
     * @var list<string>
     */
    public const TABLES = ['sisters', 'grocery_weeks', 'grocery_shares'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));

        if (! in_array($format, ['json', 'csv'], true)) {
            $this->error("Unsupported format [{$format}]. Use \"json\" or \"csv\".");

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('app/private/exports');
        File::ensureDirectoryExists($directory);

        $baseName = 'groceryshare-export-'.now()->format('Y-m-d_His');
        $tables = $this->collectTables();

        $outputPath = $format === 'json'
            ? $this->writeJson($directory, $baseName, $tables)
            : $this->writeCsv($directory, $baseName, $tables);

        $this->table(
            ['Table', 'Rows'],
            collect($tables)->map(fn (array $table, string $name): array => [$name, count($table['rows'])])->values()->all(),
        );

        $this->info("Export written to: {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * Read every exported table's columns and raw rows.
     *
     * @return array<string, array{columns: list<string>, rows: list<array<string, mixed>>}>
     */
    private function collectTables(): array
    {
        $tables = [];

        foreach (self::TABLES as $table) {
            $tables[$table] = [
                'columns' => Schema::getColumnListing($table),
                'rows' => DB::table($table)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $row): array => (array) $row)
                    ->all(),
            ];
        }

        return $tables;
    }

    /**
     * Write all tables to a single JSON file and return its path.
     *
     * @param  array<string, array{columns: list<string>, rows: list<array<string, mixed>>}>  $tables
     */
    private function writeJson(string $directory, string $baseName, array $tables): string
    {
        $path = $directory.DIRECTORY_SEPARATOR.$baseName.'.json';

        $payload = [
            'app' => config('app.name'),
            'exported_at' => now()->toIso8601String(),
            'database_driver' => DB::connection()->getDriverName(),
            'counts' => collect($tables)->map(fn (array $table): int => count($table['rows']))->all(),
            'tables' => $tables,
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * Write each table to its own CSV file inside a new folder and return the folder path.
     *
     * @param  array<string, array{columns: list<string>, rows: list<array<string, mixed>>}>  $tables
     */
    private function writeCsv(string $directory, string $baseName, array $tables): string
    {
        $folder = $directory.DIRECTORY_SEPARATOR.$baseName;
        File::ensureDirectoryExists($folder);

        foreach ($tables as $name => $table) {
            $handle = fopen($folder.DIRECTORY_SEPARATOR.$name.'.csv', 'w');

            fputcsv($handle, $table['columns'], escape: '');

            foreach ($table['rows'] as $row) {
                fputcsv(
                    $handle,
                    array_map(fn (string $column): mixed => $row[$column] ?? null, $table['columns']),
                    escape: '',
                );
            }

            fclose($handle);
        }

        return $folder;
    }
}
