<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates a route behind one or more permission keys.
 *
 * Usage: ->middleware(['auth:sanctum', 'permission:setup.manage'])
 * or for multiple (OR match): ->middleware(['auth:sanctum', 'permission:members.view,members.edit'])
 *
 * Admin roles (User::isAdmin()) bypass this check entirely. Unlike the ERP
 * reference this middleware is modeled on — where it exists but is never
 * actually attached to a route — every protected route in this app wires
 * it in from the start.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, ?string $requiredPermissions = null)
    {
        $user = $request->user();

        // Members can hold tokens too (see AuthController::login) but
        // never get into staff routes.
        if (!$user instanceof User) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (empty($requiredPermissions)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $required = array_filter(explode(',', $requiredPermissions));
        $granted = $user->permissions();

        if (empty(array_intersect($required, $granted))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
