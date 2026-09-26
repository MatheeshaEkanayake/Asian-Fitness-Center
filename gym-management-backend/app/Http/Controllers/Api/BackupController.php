<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * BackupController — Setup > Backup and Restore, backed by spatie/laravel-backup.
 *
 * Gated behind ['auth:sanctum', 'permission:setup.manage'] (see routes/api.php).
 * Frontend: src/pages/setup/BackupsPage.jsx
 * (src/context/BackupsContext.jsx, src/services/backupService.js).
 */
class BackupController extends Controller
{
    /**
     * GET /api/setup/backups
     *
     * Lists backup zip files from the configured destination disk
     * (config/backup.php → backup.destination.disks, default: 'local',
     * stored under storage/app/private/Gym Management by default).
     */
    public function index(): JsonResponse
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $appName = config('backup.backup.name');

        $files = collect($disk->allFiles($appName))
            ->filter(fn ($path) => str_ends_with($path, '.zip'))
            ->map(fn ($path) => [
                'file'       => basename($path),
                'path'       => $path,
                'size_bytes' => $disk->size($path),
                'created_at' => date('c', $disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return response()->json($files);
    }

    /**
     * POST /api/setup/backups
     *
     * Triggers a new backup run synchronously.
     */
    public function store(): JsonResponse
    {
        Artisan::call('backup:run', ['--disable-notifications' => true]);

        return response()->json(['message' => 'Backup created.', 'output' => Artisan::output()], 201);
    }

    /**
     * GET /api/setup/backups/{file}
     *
     * Streams a backup zip for download. {file} is the zip's basename.
     */
    public function show(string $file): mixed
    {
        [$disk, $path] = $this->resolve($file);

        if (!$disk->exists($path)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return $disk->download($path);
    }

    /**
     * DELETE /api/setup/backups/{file}
     */
    public function destroy(string $file): JsonResponse
    {
        [$disk, $path] = $this->resolve($file);

        if (!$disk->exists($path)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        $disk->delete($path);

        return response()->json(['message' => 'Backup deleted.']);
    }

    /**
     * @return array{0: \Illuminate\Contracts\Filesystem\Filesystem, 1: string}
     */
    private function resolve(string $file): array
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $appName = config('backup.backup.name');

        // basename() guards against path traversal via the {file} route param.
        return [$disk, $appName.'/'.basename($file)];
    }
}
