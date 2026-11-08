<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DatabaseBackUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database-back-up
                            {--no-compress : Save raw backup without gzip compression}
                            {--filename= : Custom backup filename without extension}
                            {--keep=30 : Days of previous backups to keep (older will be pruned)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a compressed MySQL database backup using mysqldump';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dbConfig = config('database.connections.mysql') ?? [];

        // Prepare destination path
        $backupDir = storage_path('app/backup');
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $shouldCompress = !$this->option('no-compress') && function_exists('gzopen');

        return $this->handleMysqlBackup($dbConfig, $backupDir, $timestamp, $shouldCompress);
    }

    /**
     * Handle MySQL / MariaDB backup using mysqldump.
     */
    protected function handleMysqlBackup(array $dbConfig, string $backupDir, string $timestamp, bool $shouldCompress): int
    {
        $dbHost = $dbConfig['host'] ?? '127.0.0.1';
        $dbPort = $dbConfig['port'] ?? 3306;
        $dbName = $dbConfig['database'] ?? null;
        if (empty($dbName) || $dbName === ':memory:') {
            $dbName = config('database.connections.mysql.database');
            if (empty($dbName) || $dbName === ':memory:') {
                $dbName = 'skynet_pos_ecommerce';
            }
        }
        $dbUser = $dbConfig['username'] ?? 'root';
        $dbPass = (string) ($dbConfig['password'] ?? '');

        if (empty($dbName)) {
            $this->error('Database name is not configured in config/database.php.');
            Log::error('Database backup failed: DB_DATABASE is not configured.');
            return self::FAILURE;
        }

        // 1. Locate mysqldump binary
        $mysqldump = $this->resolveMysqldumpPath();
        if (!$mysqldump) {
            $this->error('Could not locate the `mysqldump` executable. Please add it to your system PATH or configure MYSQLDUMP_PATH.');
            Log::error('Database backup failed: mysqldump binary not found.');
            return self::FAILURE;
        }

        $baseName = $this->option('filename') ?: "backup-{$dbName}-{$timestamp}";
        $sqlPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
        $finalPath = $shouldCompress ? $sqlPath . '.gz' : $sqlPath;

        $this->info("Starting backup for database: [{$dbName}]...");
        $this->line("Using binary: {$mysqldump}");

        // 2. Build dump process
        $command = [
            $mysqldump,
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--user=' . $dbUser,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
        ];

        if ($dbPass !== '') {
            $command[] = '--password=' . $dbPass;
        }

        $command[] = $dbName;

        $env = [
            'MYSQL_PWD' => $dbPass,
        ];

        // Ensure critical Windows environment variables are provided so Winsock TCP/IP provider can load (prevents error 10106)
        if (DIRECTORY_SEPARATOR === '\\' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $systemRoot = getenv('SystemRoot') ?: (getenv('WINDIR') ?: 'C:\\WINDOWS');
            if (!$systemRoot || $systemRoot === 'false') {
                $systemRoot = 'C:\\WINDOWS';
            }
            $env['SystemRoot'] = $systemRoot;
            $env['WINDIR'] = $systemRoot;
            $env['SYSTEMDRIVE'] = getenv('SYSTEMDRIVE') ?: (substr($systemRoot, 0, 2) ?: 'C:');
            $env['PATH'] = getenv('PATH') ?: ($systemRoot . '\\system32;' . $systemRoot);
            $env['TEMP'] = getenv('TEMP') ?: sys_get_temp_dir();
            $env['TMP'] = getenv('TMP') ?: sys_get_temp_dir();
            $env['COMSPEC'] = getenv('COMSPEC') ?: ($systemRoot . '\\system32\\cmd.exe');
        } elseif ($path = getenv('PATH')) {
            $env['PATH'] = $path;
        }

        // Inherit any existing environment variables that were not explicitly overridden
        foreach (array_merge($_SERVER, $_ENV) as $k => $v) {
            if (is_string($v) && $v !== '' && !isset($env[$k])) {
                $env[$k] = $v;
            }
        }

        $process = new Process($command, null, $env, null, 600); // 10 minutes timeout

        // Pipe output directly to file to prevent high memory usage
        $fileHandle = fopen($sqlPath, 'w');
        if (!$fileHandle) {
            $this->error("Failed to open temporary file for writing: {$sqlPath}");
            return self::FAILURE;
        }

        try {
            $process->run(function ($type, $buffer) use ($fileHandle) {
                if ($type === Process::OUT) {
                    fwrite($fileHandle, $buffer);
                }
            });
        } finally {
            fclose($fileHandle);
        }

        if (!$process->isSuccessful()) {
            File::delete($sqlPath);
            $errorOutput = trim($process->getErrorOutput());
            Log::error("Database backup failed: {$errorOutput}");
            $this->error("Database backup failed: " . ($errorOutput ?: 'Unknown error.'));
            return self::FAILURE;
        }

        // 3. Compress to .gz natively via PHP
        if ($shouldCompress) {
            $this->line("Compressing backup with gzip...");
            $compressed = $this->compressWithGzip($sqlPath, $finalPath);
            File::delete($sqlPath);

            if (!$compressed) {
                $this->error("Gzip compression failed. Uncompressed backup retained.");
                return self::FAILURE;
            }
        }

        return $this->finalizeBackup($finalPath, $backupDir);
    }

    /**
     * Record stats, activity log, and prune older backups.
     */
    protected function finalizeBackup(string $finalPath, string $backupDir): int
    {
        $size = File::size($finalPath);
        $formattedSize = $this->formatBytes($size);
        $filename = basename($finalPath);

        $this->info("✓ Database backup completed successfully!");
        $this->line("  File: <comment>{$finalPath}</comment>");
        $this->line("  Size: <comment>{$formattedSize}</comment>");

        Log::info("Database backup created: {$finalPath} ({$formattedSize})");

        ActivityLogger::system("Created database backup: {$filename} ({$formattedSize})", [
            'file'          => $filename,
            'path'          => $finalPath,
            'size'          => $size,
            'size_human'    => $formattedSize,
            'created_at'    => now()->toDateTimeString(),
        ]);

        // Prune older backups
        $keepDays = (int) $this->option('keep');
        if ($keepDays > 0) {
            $this->pruneOldBackups($backupDir, $keepDays);
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the absolute path to mysqldump binary.
     */
    protected function resolveMysqldumpPath(): ?string
    {
        // 1. Check custom path in env / config
        $customPath = env('MYSQLDUMP_PATH') ?: config('database.connections.mysql.dump.dump_binary_path');
        if ($customPath && File::exists($customPath)) {
            return $customPath;
        }

        // 2. Test if `mysqldump` is globally accessible in PATH
        $testProcess = new Process(['mysqldump', '--version']);
        $testProcess->run();
        if ($testProcess->isSuccessful()) {
            return 'mysqldump';
        }

        // 3. Common fallback locations on Windows
        $windowsCandidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\current\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 10.4\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 11.4\\bin\\mysqldump.exe',
        ];

        foreach ($windowsCandidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        // 4. Common fallback locations on Unix/Linux
        $unixCandidates = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
        ];

        foreach ($unixCandidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Stream-compress a file to .gz natively via PHP (no external gzip CLI needed).
     */
    protected function compressWithGzip(string $sourcePath, string $destPath): bool
    {
        $srcHandle = fopen($sourcePath, 'rb');
        $gzHandle  = gzopen($destPath, 'wb9');

        if (!$srcHandle || !$gzHandle) {
            if ($srcHandle) fclose($srcHandle);
            if ($gzHandle)  gzclose($gzHandle);
            return false;
        }

        while (!feof($srcHandle)) {
            gzwrite($gzHandle, fread($srcHandle, 1024 * 512)); // 512 KB chunks
        }

        fclose($srcHandle);
        gzclose($gzHandle);

        return true;
    }

    /**
     * Delete backups older than $days in the backup directory.
     */
    protected function pruneOldBackups(string $directory, int $days): void
    {
        $threshold = Carbon::now()->subDays($days)->timestamp;
        $files = File::files($directory);
        $deleted = 0;

        foreach ($files as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getRealPath());
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->line("Pruned {$deleted} backup(s) older than {$days} days.");
            Log::info("Pruned {$deleted} old database backup file(s).");
        }
    }

    /**
     * Format byte count into human readable units.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

