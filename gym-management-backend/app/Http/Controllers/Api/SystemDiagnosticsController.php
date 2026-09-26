<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * SystemDiagnosticsController — Setup > System Diagnostics, read-only.
 *
 * Gated behind ['auth:sanctum', 'permission:setup.manage'] (see routes/api.php).
 * Stateless — no model, no FormRequest.
 * Frontend: src/pages/setup/SystemDiagnosticsPage.jsx
 * (src/services/diagnosticsService.js — local useState/useEffect, no Context).
 */
class SystemDiagnosticsController extends Controller
{
    /**
     * GET /api/setup/diagnostics
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'php_version'       => PHP_VERSION,
            'laravel_version'   => app()->version(),
            'database'          => $this->databaseStatus(),
            'storage_writable'  => is_writable(storage_path()),
            'cache_driver'      => config('cache.default'),
            'queue_driver'      => config('queue.default'),
            'disk_free_bytes'   => disk_free_space(base_path()),
            'disk_total_bytes'  => disk_total_space(base_path()),
        ]);
    }

    private function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return ['connected' => true, 'driver' => DB::connection()->getDriverName()];
        } catch (\Throwable $e) {
            return ['connected' => false, 'driver' => config('database.default')];
        }
    }
}
