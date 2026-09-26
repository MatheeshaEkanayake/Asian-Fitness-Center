<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

/**
 * BranchController — Setup > Branches CRUD.
 *
 * The gym is single-location today; this exists so multi-location expansion
 * later is just adding rows through this already-working screen, not a
 * schema change. All routes are gated behind
 * ['auth:sanctum', 'permission:setup.manage'] (see routes/api.php).
 * Frontend: src/pages/setup/BranchesListPage.jsx, BranchFormPage.jsx
 * (src/context/BranchesContext.jsx, src/services/branchService.js).
 */
class BranchController extends Controller
{
    /**
     * GET /api/setup/branches
     */
    public function index(): JsonResponse
    {
        return response()->json(Branch::orderBy('name')->get());
    }

    /**
     * GET /api/setup/branches/{id}
     */
    public function show(Branch $branch): JsonResponse
    {
        return response()->json($branch);
    }

    /**
     * POST /api/setup/branches
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return response()->json($branch, 201);
    }

    /**
     * PUT /api/setup/branches/{id}
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return response()->json($branch->fresh());
    }

    /**
     * DELETE /api/setup/branches/{id}
     *
     * Blocked for the default branch or any branch still referenced by a
     * user, to avoid orphaning accounts.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        if ($branch->is_default) {
            return response()->json(['message' => 'The default branch cannot be deleted.'], 422);
        }

        if ($branch->users()->exists()) {
            return response()->json(['message' => 'This branch is still assigned to one or more users.'], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }
}
