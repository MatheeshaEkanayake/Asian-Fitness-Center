<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * RoleController — Setup > Roles CRUD, including the permission checklist.
 *
 * All routes are gated behind ['auth:sanctum', 'permission:setup.manage']
 * (see routes/api.php). Frontend: src/pages/setup/RolesListPage.jsx,
 * RoleFormPage.jsx + src/components/setup/PermissionsChecklist.jsx
 * (src/context/RolesContext.jsx, src/services/roleService.js).
 */
class RoleController extends Controller
{
    /**
     * GET /api/setup/roles
     */
    public function index(): JsonResponse
    {
        return response()->json(Role::orderBy('name')->get());
    }

    /**
     * GET /api/setup/roles/{id}
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json($role);
    }

    /**
     * POST /api/setup/roles
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());

        return response()->json($role, 201);
    }

    /**
     * PUT /api/setup/roles/{id}
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role->update($request->validated());

        return response()->json($role->fresh());
    }

    /**
     * DELETE /api/setup/roles/{id}
     *
     * Roles have no dependent history worth preserving (unlike Members/Users),
     * so this is a real delete — but blocked for the seeded system role or
     * any role still assigned to a user, to avoid orphaning accounts.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'This role is required by the system and cannot be deleted.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'This role is still assigned to one or more users.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
