<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('db:restore {file? : The backup file to restore (defaults to latest backup)}')]
#[Description('Restore database tables and records from a backup file in database/backups')]
class RestoreDatabaseCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = database_path('backups');
        $fileName = $this->argument('file');

        if ($fileName) {
            $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '', basename((string) $fileName));
            if (! str_ends_with($safeName, '.json')) {
                $safeName .= '.json';
            }
            $filePath = "{$backupDir}/{$safeName}";
        } else {
            $files = File::glob("{$backupDir}/*.json");
            if (empty($files)) {
                $this->error('No backup files found in database/backups!');

                return self::FAILURE;
            }
            rsort($files);
            $filePath = $files[0];
        }

        if (! File::exists($filePath)) {
            $this->error("Backup file not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Restoring database from: {$filePath}");

        $data = json_decode(File::get($filePath), true);
        if (! is_array($data) || empty($data['tables'])) {
            $this->error('Invalid backup file format.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($data): void {
                foreach ($data['tables'] as $table => $tableData) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    DB::table($table)->delete();
                    $rows = $tableData['rows'] ?? [];

                    if (! empty($rows)) {
                        foreach (array_chunk($rows, 100) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }

                    $this->line("  ✓ Table [{$table}]: ".count($rows).' rows restored.');
                }
            });
        } catch (\Throwable $e) {
            $this->error('Database restoration failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Database restoration completed successfully!');

        return self::SUCCESS;
    }
}
