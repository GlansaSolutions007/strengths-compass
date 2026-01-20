<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DatabaseController extends Controller
{
    /**
     * Download database SQL file
     * Supports MySQL and SQLite databases
     */
    public function downloadDatabase(Request $request)
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $filename = null;
            $sqlContent = null;

            switch ($driver) {
                case 'mysql':
                    $sqlContent = $this->exportMySQL();
                    $filename = 'database_backup_' . now()->format('Y-m-d_His') . '.sql';
                    break;

                case 'sqlite':
                    $sqlContent = $this->exportSQLite();
                    $filename = 'database_backup_' . now()->format('Y-m-d_His') . '.sql';
                    break;

                default:
                    return response()->json([
                        'data' => [],
                        'status' => 400,
                        'message' => 'Database driver not supported. Only MySQL and SQLite are supported.',
                    ], 400);
            }

            if ($sqlContent === null || empty($sqlContent)) {
                return response()->json([
                    'data' => [],
                    'status' => 500,
                    'message' => 'Failed to generate database backup',
                ], 500);
            }

            // Return SQL file as download
            return response($sqlContent, 200)
                ->header('Content-Type', 'application/sql')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($sqlContent));

        } catch (\Exception $e) {
            \Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Failed to generate database backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export MySQL database using mysqldump or mariadb-dump
     */
    private function exportMySQL(): ?string
    {
        $config = config('database.connections.mysql');
        
        // Use 127.0.0.1 instead of localhost to force IPv4 and avoid IPv6 issues
        $host = $config['host'] ?? '127.0.0.1';
        if (strtolower($host) === 'localhost') {
            $host = '127.0.0.1';
        }
        
        $port = $config['port'] ?? '3306';
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $socket = $config['unix_socket'] ?? '';

        if (empty($database) || empty($username)) {
            throw new \Exception('MySQL database configuration is incomplete');
        }

        // Try mariadb-dump first (for MariaDB), then fallback to mysqldump
        $dumpCommands = ['mariadb-dump', 'mysqldump'];
        $lastError = null;

        foreach ($dumpCommands as $dumpCommand) {
            try {
                // Check if command exists
                $checkProcess = Process::fromShellCommandline("which {$dumpCommand}");
                $checkProcess->run();
                
                if (!$checkProcess->isSuccessful()) {
                    continue; // Try next command
                }

                // Build dump command
                // Use environment variable for password to avoid shell escaping issues
                $env = [
                    'MYSQL_PWD' => $password,
                ];

                $commandParts = [
                    $dumpCommand,
                    '--host=' . escapeshellarg($host),
                    '--port=' . escapeshellarg($port),
                    '--user=' . escapeshellarg($username),
                    '--single-transaction',
                    '--routines',
                    '--triggers',
                    '--no-tablespaces', // Avoid permission issues
                ];

                // Use socket if available (more reliable than TCP)
                if (!empty($socket) && file_exists($socket)) {
                    $commandParts[] = '--socket=' . escapeshellarg($socket);
                    // Remove host/port when using socket
                    $commandParts = array_filter($commandParts, function($part) {
                        return substr($part, 0, 7) !== '--host=' && substr($part, 0, 7) !== '--port=';
                    });
                }

                $commandParts[] = escapeshellarg($database);

                $command = implode(' ', $commandParts);

                // Execute dump command
                $process = Process::fromShellCommandline($command);
                $process->setEnv($env);
                $process->setTimeout(300); // 5 minutes timeout
                $process->run();

                if ($process->isSuccessful()) {
                    return $process->getOutput();
                }

                $lastError = $process->getErrorOutput();
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                continue; // Try next command
            }
        }

        // If all commands failed, try programmatic export as fallback
        \Log::warning('MySQL dump commands failed, attempting programmatic export', [
            'error' => $lastError
        ]);

        return $this->exportMySQLProgrammatic($config);
    }

    /**
     * Export MySQL database programmatically using Laravel DB facade
     * This is a fallback when mysqldump/mariadb-dump is not available
     */
    private function exportMySQLProgrammatic(array $config): string
    {
        $sql = "-- MySQL Database Dump\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
        $sql .= "-- Note: This is a programmatic export. Some features may differ from mysqldump.\n\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "START TRANSACTION;\n";
        $sql .= "SET time_zone = \"+00:00\";\n\n";

        // Get all tables
        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $config['database'];

        foreach ($tables as $table) {
            // Handle dynamic property access safely
            $tableArray = (array) $table;
            $tableName = $tableArray[$tableKey] ?? reset($tableArray);
            
            // Get table structure
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($createTable)) {
                $createTableArray = (array) $createTable[0];
                $createTableSql = $createTableArray['Create Table'] ?? null;
                if ($createTableSql) {
                    $sql .= "-- Table structure for table `{$tableName}`\n";
                    $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    $sql .= $createTableSql . ";\n\n";
                }
            }

            // Get table data
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                $sql .= "-- Dumping data for table `{$tableName}`\n";
                foreach ($rows as $row) {
                    $columns = array_keys((array)$row);
                    $values = array_map(function ($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        if (is_numeric($value)) {
                            return $value;
                        }
                        // Escape single quotes and backslashes
                        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
                    }, array_values((array)$row));

                    $sql .= sprintf(
                        "INSERT INTO `%s` (`%s`) VALUES (%s);\n",
                        $tableName,
                        implode('`, `', $columns),
                        implode(', ', $values)
                    );
                }
                $sql .= "\n";
            }
        }

        $sql .= "COMMIT;\n";

        return $sql;
    }

    /**
     * Export SQLite database
     */
    private function exportSQLite(): ?string
    {
        $config = config('database.connections.sqlite');
        $databasePath = $config['database'] ?? database_path('database.sqlite');

        if (!file_exists($databasePath)) {
            throw new \Exception('SQLite database file not found: ' . $databasePath);
        }

        // Try using sqlite3 command first (if available)
        $command = sprintf('sqlite3 %s .dump', escapeshellarg($databasePath));

        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if ($process->isSuccessful()) {
                return $process->getOutput();
            }
        } catch (\Exception $e) {
            // Fallback to programmatic export
            \Log::warning('SQLite command export failed, using programmatic export', [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: Generate SQL dump programmatically using Laravel
        return $this->exportSQLiteProgrammatic($databasePath);
    }

    /**
     * Export SQLite database programmatically
     */
    private function exportSQLiteProgrammatic(string $databasePath): string
    {
        $sql = "-- SQLite Database Dump\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n\n";
        $sql .= "BEGIN TRANSACTION;\n\n";

        // Get all tables
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $table) {
            $tableName = $table->name;
            
            // Get table schema
            $schema = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);
            if (!empty($schema) && isset($schema[0]->sql)) {
                $sql .= $schema[0]->sql . ";\n\n";
            }

            // Get table data
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                foreach ($rows as $row) {
                    $columns = array_keys((array)$row);
                    $values = array_map(function ($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        if (is_numeric($value)) {
                            return $value;
                        }
                        return "'" . str_replace("'", "''", $value) . "'";
                    }, array_values((array)$row));

                    $sql .= sprintf(
                        "INSERT INTO %s (%s) VALUES (%s);\n",
                        $tableName,
                        implode(', ', $columns),
                        implode(', ', $values)
                    );
                }
                $sql .= "\n";
            }
        }

        $sql .= "COMMIT;\n";

        return $sql;
    }

    /**
     * Get database information (for debugging/admin purposes)
     */
    public function getDatabaseInfo()
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $config = config("database.connections.{$driver}");

            $info = [
                'driver' => $driver,
                'database_name' => $config['database'] ?? 'N/A',
                'host' => $config['host'] ?? 'N/A',
                'port' => $config['port'] ?? 'N/A',
            ];

            // Get database size if possible
            try {
                if ($driver === 'mysql') {
                    $databaseName = $config['database'];
                    $sizeQuery = DB::select("SELECT 
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                        FROM information_schema.tables 
                        WHERE table_schema = ?", [$databaseName]);
                    $info['size_mb'] = $sizeQuery[0]->size_mb ?? 'N/A';
                } elseif ($driver === 'sqlite') {
                    $databasePath = $config['database'] ?? database_path('database.sqlite');
                    if (file_exists($databasePath)) {
                        $sizeBytes = filesize($databasePath);
                        $info['size_mb'] = round($sizeBytes / 1024 / 1024, 2);
                    }
                }
            } catch (\Exception $e) {
                $info['size_mb'] = 'Unable to calculate';
            }

            return response()->json([
                'data' => $info,
                'status' => 200,
                'message' => 'Database information retrieved successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'status' => 500,
                'message' => 'Failed to get database information: ' . $e->getMessage(),
            ], 500);
        }
    }
}
