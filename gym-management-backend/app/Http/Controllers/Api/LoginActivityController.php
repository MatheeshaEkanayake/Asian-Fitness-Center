<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LoginActivityController — Setup > Login Activity, read-only audit trail.
 *
 * Gated behind ['auth:sanctum', 'permission:setup.manage'] (see routes/api.php).
 * Frontend: src/pages/setup/LoginActivityPage.jsx
 * (src/services/loginActivityService.js — no Context, single read-only consumer).
 */
class LoginActivityController extends Controller
{
    /**
     * GET /api/setup/login-activity
     *
     * ?per_page={int} — default 50, clamped to 1–100.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        return response()->json(
            LoginActivity::with('user:id,full_name,email')
                ->orderByDesc('logged_in_at')
                ->paginate($perPage)
        );
    }
}
