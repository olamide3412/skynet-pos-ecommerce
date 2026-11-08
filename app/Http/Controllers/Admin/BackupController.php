<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * Authorize access strictly for Super Administrators.
     */
    protected function authorizeAdmin(): void
    {
        $user = auth('web')->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Database backups are restricted to Super Administrators.');
        }
    }

    /**
     * List all database backups in storage.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $backupDir = storage_path('app/backup');
        $backups = [];

        if (File::isDirectory($backupDir)) {
            $files = File::files($backupDir);

            // Sort newest first
            usort($files, function ($a, $b) {
                return $b->getMTime() <=> $a->getMTime();
            });

            foreach ($files as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['gz', 'sql'], true)) {
                    continue;
                }

                $backups[] = [
                    'name'         => $file->getFilename(),
                    'size'         => $this->formatBytes($file->getSize()),
                    'raw_size'     => $file->getSize(),
                    'created_at'   => Carbon::createFromTimestamp($file->getMTime())->format('M d, Y h:i A'),
                    'age'          => Carbon::createFromTimestamp($file->getMTime())->diffForHumans(),
                ];
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'backups' => $backups,
            ]);
        }

        return Inertia::render('Admin/Backups/Index', [
            'backups' => $backups,
        ]);
    }

    /**
     * Trigger manual database backup.
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        try {
            $exitCode = Artisan::call('app:database-back-up');

            if ($exitCode === 0) {
                $user = auth('web')->user();
                ActivityLogger::system("Manual database backup triggered by admin {$user?->name}", [
                    'admin_id'   => $user?->id,
                    'admin_name' => $user?->name,
                ]);

                return back()->with('success', 'Database backup created and compressed successfully!');
            }

            return back()->with('error', 'Database backup failed to complete. Please check system logs.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Download a specific backup file.
     */
    public function download(Request $request, ?string $filename = null): BinaryFileResponse
    {
        $this->authorizeAdmin();

        $targetName = $filename ?: $request->query('filename');
        if (empty($targetName)) {
            abort(400, 'Missing backup filename.');
        }

        $safeFilename = basename($targetName);
        $filePath = storage_path('app/backup/' . $safeFilename);

        if (!File::exists($filePath)) {
            abort(404, 'Backup file not found.');
        }

        $mimeType = str_ends_with($safeFilename, '.gz') ? 'application/x-gzip' : 'application/sql';

        return response()->download($filePath, $safeFilename, [
            'Content-Type'              => $mimeType,
            'Content-Disposition'       => 'attachment; filename="' . $safeFilename . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control'             => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Delete an old backup file.
     */
    public function destroy(Request $request, ?string $filename = null)
    {
        $this->authorizeAdmin();

        $targetName = $filename ?: $request->query('filename') ?: $request->input('filename');
        if (empty($targetName)) {
            abort(400, 'Missing backup filename.');
        }

        $safeFilename = basename($targetName);
        $filePath = storage_path('app/backup/' . $safeFilename);

        if (File::exists($filePath)) {
            File::delete($filePath);

            $user = auth('web')->user();
            ActivityLogger::system("Deleted database backup {$safeFilename} by admin {$user?->name}", [
                'file'       => $safeFilename,
                'admin_id'   => $user?->id,
                'admin_name' => $user?->name,
            ]);

            return back()->with('success', "Backup '{$safeFilename}' was deleted successfully.");
        }

        return back()->with('error', 'Backup file not found.');
    }

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

