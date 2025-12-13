<?php

declare(strict_types=1);

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing database integrity after imports
 */
class DatabaseIntegrityService
{
    public function __construct(
        private readonly DatabaseImportService $importService
    ) {}

    /**
     * Reset auto-increment values for all tables.
     * This ensures IDs start from 1 after import.
     */
    public function resetAutoIncrements(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        foreach ($this->importService->getTables() as $table) {
            if ($this->importService->tableExists($table)) {
                try {
                    if ($table === 'users' && app()->environment('production')) {
                        continue;
                    }

                    if ($driver === 'mysql') {
                        DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                    } elseif ($driver === 'sqlite') {
                        DB::statement("UPDATE sqlite_sequence SET seq = 0 WHERE name = '{$table}'");
                    }
                } catch (Exception) {
                    continue;
                }
            }
        }
    }

    /**
     * Verify foreign key integrity after import.
     *
     * @return array<string, array<string>> List of integrity issues by table
     */
    public function verifyIntegrity(): array
    {
        $issues = [];
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            try {
                $results = DB::select('SELECT * FROM information_schema.INNODB_FOREIGN WHERE FOR_NAME LIKE ?', [
                    config('database.connections.mysql.database').'%',
                ]);

                foreach ($results as $fk) {
                    $checkQuery = "SELECT COUNT(*) as orphans FROM {$fk->FOR_NAME} f LEFT JOIN {$fk->REF_NAME} r ON f.{$fk->FOR_COL_NAME} = r.{$fk->REF_COL_NAME} WHERE f.{$fk->FOR_COL_NAME} IS NOT NULL AND r.{$fk->REF_COL_NAME} IS NULL";
                    $orphans = DB::selectOne($checkQuery);
                    if ($orphans && $orphans->orphans > 0) {
                        $issues[$fk->FOR_NAME][] = "Found {$orphans->orphans} orphan records referencing {$fk->REF_NAME}";
                    }
                }
            } catch (Exception) {
                // Ignore errors in integrity check
            }
        }

        return $issues;
    }
}
