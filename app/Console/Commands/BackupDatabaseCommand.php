<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('db:backup {--file= : Custom output file name}')]
#[Description('Safely backup database tables, schema definitions, and records into database/backups')]
class BackupDatabaseCommand extends Command
{
    /**
     * Tables to include in backup in topological order.
     *
     * @var list<string>
     */
    protected array $tables = [
        'users',
        'categories',
        'folders',
        'notes',
        'note_chunks',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = database_path('backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $customName = $this->option('file');
        $baseName = $customName ? preg_replace('/[^a-zA-Z0-9_-]/', '', basename((string) $customName)) : "notenux_backup_{$timestamp}";
        if (empty($baseName)) {
            $baseName = "notenux_backup_{$timestamp}";
        }

        $jsonFile = "{$backupDir}/{$baseName}.json";
        $sqlFile = "{$backupDir}/{$baseName}.sql";

        $this->info("Initiating database backup to: {$backupDir}");

        $backupData = [
            'created_at' => now()->toIso8601String(),
            'database' => config('database.default'),
            'tables' => [],
        ];

        $sqlContent = "-- Notenux Database Backup\n";
        $sqlContent .= '-- Generated at: '.now()->toIso8601String()."\n";
        $sqlContent .= '-- Connection: '.config('database.default')."\n\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $backupData['tables'][$table] = [
                'count' => count($rows),
                'rows' => $rows,
            ];

            $this->line("  ✓ Table [{$table}]: ".count($rows).' rows backed up.');

            if (! empty($rows)) {
                $sqlContent .= "-- Table: {$table} (".count($rows)." rows)\n";
                $pdo = DB::connection()->getPdo();
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $escapedKeys = array_map(fn ($k) => "`{$k}`", $keys);
                    $escapedValues = array_map(function ($val) use ($pdo) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        if (is_int($val) || is_float($val)) {
                            return (string) $val;
                        }

                        return $pdo ? $pdo->quote((string) $val) : "'".addslashes((string) $val)."'";
                    }, array_values($row));

                    $sqlContent .= 'INSERT INTO `'.$table.'` ('.implode(', ', $escapedKeys).') VALUES ('.implode(', ', $escapedValues).");\n";
                }
                $sqlContent .= "\n";
            }
        }

        $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";

        File::put($jsonFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put($sqlFile, $sqlContent);

        $this->info("Backup complete!\n  JSON: {$jsonFile}\n  SQL:  {$sqlFile}");

        return self::SUCCESS;
    }
}
