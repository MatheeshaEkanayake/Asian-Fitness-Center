<?php

namespace App\Support;

/**
 * Canonical permission-key registry, mirrored on the frontend by
 * src/config/navigationTree.js. Kept as a single source of truth so
 * StoreRoleRequest/UpdateRoleRequest can validate `permissions` entries
 * against a known list instead of accepting arbitrary strings.
 *
 * Deliberately flat string keys ("resource.action"), not the ERP's
 * semicolon-delimited numeric-id scheme — there's no shared build-time
 * registry (e.g. a TS enum) on this stack to justify numeric ids.
 */
class Permissions
{
    public const ALL = [
        'dashboard.view',
        'members.view',
        'members.edit',
        'members.gate_access',
        'members.delete',
        'members.review',
        'members.review_edit',
        'payments.view',
        'payments.edit',
        'payments.delete',
        'payments_audit.view',
        'payments_audit_edit',
        'attendance.view',
        'attendance.edit',
        'inventory.view',
        'inventory_edit',
        'stock.view',
        'stock_edit',
        'dealers.view',
        'dealers_edit',
        'inventory_audit.view',
        'inventory_audit_edit',
        'exercise_plan.view',
        'exercise_plan_edit',
        'meal_plan.view',
        'meal_plan_edit',
        'setup.manage',
    ];
}
