<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;

#[Signature('app:import-data
    {file : Path to a JSON file created by app:export-data}
    {--force : Delete existing sisters, weeks and shares before importing}')]
#[Description('Import GroceryShare data from a JSON export created by app:export-data')]
class ImportData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! File::isFile($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        try {
            $export = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error("Invalid JSON: {$exception->getMessage()}");

            return self::FAILURE;
        }

        foreach (ExportData::TABLES as $table) {
            if (! is_array($export['tables'][$table]['rows'] ?? null)) {
                $this->error("Export is missing the [{$table}] table.");

                return self::FAILURE;
            }
        }

        $hasExistingData = collect(ExportData::TABLES)->contains(fn (string $table): bool => DB::table($table)->exists());

        if ($hasExistingData && ! $this->option('force')) {
            $this->error('The database already has data. Re-run with --force to replace it.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($export): void {
                // Children first so foreign keys never point at a deleted parent.
                foreach (array_reverse(ExportData::TABLES) as $table) {
                    DB::table($table)->delete();
                }

                foreach (ExportData::TABLES as $table) {
                    // Chunked to stay under SQLite's bound-parameter limit.
                    foreach (array_chunk($export['tables'][$table]['rows'], 100) as $rows) {
                        DB::table($table)->insert($rows);
                    }
                }
            });
        } catch (QueryException $exception) {
            $this->error("Import failed, nothing was changed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Rows'],
            collect(ExportData::TABLES)->map(fn (string $table): array => [$table, count($export['tables'][$table]['rows'])])->all(),
        );

        $this->info("Imported from: {$file}");

        return self::SUCCESS;
    }
}
