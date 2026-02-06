<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class ImportDatabaseDump extends Command
{
    protected $signature = 'db:import
                            {file? : Path to the SQL file (defaults to public/import.sql)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Drop all tables and import a SQL dump file into the current database';

    public function handle(): int
    {
        $file = $this->argument('file') ?? public_path('import.sql');

        if (! file_exists($file)) {
            $this->error("SQL file not found: {$file}");

            return self::FAILURE;
        }

        $fileSize = round(filesize($file) / 1024 / 1024, 2);
        $database = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $this->info("SQL file: {$file} ({$fileSize} MB)");
        $this->info("Target database: {$database} @ {$host}:{$port}");

        if (! $this->option('force') && ! $this->confirm(
            "This will DROP ALL TABLES in '{$database}' and import the dump. Continue?"
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // Step 1: Drop all existing tables
        $this->info('Dropping all existing tables...');

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $tables = DB::select('SHOW TABLES');
            $key = "Tables_in_{$database}";

            foreach ($tables as $table) {
                $tableName = $table->$key;
                DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                $this->line("  Dropped: {$tableName}");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            $this->info('All tables dropped successfully.');
        } catch (\Throwable $e) {
            $this->error('Failed to drop tables: '.$e->getMessage());

            return self::FAILURE;
        }

        // Step 2: Import the SQL dump via mysql CLI
        $this->info('Importing SQL dump (this may take a moment)...');

        $command = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($file)
        );

        $result = Process::timeout(300)->run($command);

        if (! $result->successful()) {
            $output = $result->output().$result->errorOutput();

            // Filter out display width warnings (MariaDB -> MySQL compat)
            $lines = array_filter(
                explode("\n", $output),
                fn (string $line) => $line !== '' && ! str_contains($line, 'Integer display width is deprecated')
            );

            if (! empty($lines)) {
                $this->error('Import failed with errors:');
                foreach ($lines as $line) {
                    $this->line("  {$line}");
                }

                return self::FAILURE;
            }
        }

        // Step 3: Verify import
        $tables = DB::select('SHOW TABLES');
        $tableCount = count($tables);

        if ($tableCount === 0) {
            $this->error('Import appears to have failed - no tables found in database.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Import completed successfully! {$tableCount} tables imported into '{$database}'.");

        return self::SUCCESS;
    }
}
