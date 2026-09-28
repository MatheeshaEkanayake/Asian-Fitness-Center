<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DeviceEnrollmentController;
use App\Http\Controllers\Api\EmailSettingsController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\GymSettingsController;
use App\Http\Controllers\Api\LoginActivityController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentPlanController;
use App\Http\Controllers\Api\PaymentSummaryController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StaffAttendanceController;
use App\Http\Controllers\Api\SystemDiagnosticsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VftWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes replace all the async service functions in the React frontend
| that were reading from / writing to the in-memory db.js store.
|
| Frontend service files replaced by these routes:
|   src/services/memberService.js   → /api/members/*
|   src/services/paymentService.js  → /api/payments/*, /api/payments/summary
|   src/data/mockData.js            → Seeded via database/seeders/
|
| Context providers replaced:
|   src/context/MembersContext.jsx  → /api/members/*
|   src/context/PaymentsContext.jsx → /api/payments/*, /api/payments/summary
|
| NOTE: this domain was originally exposed under /api/transactions/* — the
| table/model was renamed transactions → payments (Transaction → Payment) so
| DB/API naming matches "payment" terminology used elsewhere. The frontend's
| paymentService.js keeps its existing function/variable names unchanged
| (transaction, transactions, etc.); only the endpoint paths it calls moved.
|
*/

// -----------------------------------------------------------------------
// Auth
// Replaces: src/layout/SidePanel.jsx static "Signed in as..." placeholder
// Token mode (Authorization: Bearer <token>), no cookie/SPA auth, no
// email-token password reset — see AuthController.
// -----------------------------------------------------------------------
// Everyone registers via signup and signs in via login (staff are
// registrations an admin granted a role). Public, so throttled per IP —
// login more loosely, since a whole gym can share one Wi-Fi IP.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:30,1');
Route::post('/auth/signup', [AuthController::class, 'signup'])->middleware('throttle:10,1');
// Door punches pushed by the VFT cloud. No login — the secret in the URL
// is checked in VftWebhookController (404 if wrong or unset).
Route::post('/vft/webhook/{secret}', VftWebhookController::class)->middleware('throttle:120,1');
Route::get('/public/payment-plans', [PaymentPlanController::class, 'publicIndex'])->middleware('throttle:60,1');

// Gym branding (name, tagline, contact details, logo) — public so the
// sign-in pages can show it. Edited via Setup > Gym Settings below.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/gym/branding', [GymSettingsController::class, 'branding']);
    Route::get('/gym/logo', [GymSettingsController::class, 'logo']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // -------------------------------------------------------------------
    // Setup — Users, Roles, Branches
    // Gated behind a single 'setup.manage' permission (not one flag per
    // sub-screen) — see app/Http/Middleware/CheckPermission.php.
    // Frontend: src/pages/setup/*
    // -------------------------------------------------------------------
    Route::middleware('permission:setup.manage')->prefix('setup')->group(function () {
        Route::get('users/candidates', [UserController::class, 'candidates']);
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('users/{user}/enroll', [DeviceEnrollmentController::class, 'staff']);
        Route::get('users/{user}/enrollments', [DeviceEnrollmentController::class, 'staffStatus']);

        Route::apiResource('roles', RoleController::class);

        Route::apiResource('branches', BranchController::class);

        Route::post('payment-plans', [PaymentPlanController::class, 'store']);
        Route::put('payment-plans/{paymentPlan}', [PaymentPlanController::class, 'update']);
        Route::patch('payment-plans/{paymentPlan}/status', [PaymentPlanController::class, 'updateStatus']);

        Route::get('gym', [GymSettingsController::class, 'show']);
        Route::put('gym', [GymSettingsController::class, 'update']);

        Route::get('email', [EmailSettingsController::class, 'show']);
        Route::put('email', [EmailSettingsController::class, 'update']);
        Route::post('email/test', [EmailSettingsController::class, 'testEmail']);

        Route::get('login-activity', [LoginActivityController::class, 'index']);

        Route::get('diagnostics', [SystemDiagnosticsController::class, 'index']);

        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'store']);
        Route::get('backups/{file}', [BackupController::class, 'show']);
        Route::delete('backups/{file}', [BackupController::class, 'destroy']);
    });
});

// -----------------------------------------------------------------------
// Members, Transactions, Payments Summary, Attendance
//
// All gated behind auth:sanctum + a members.view|members.edit /
// payments.view|payments.edit / attendance.view|attendance.edit permission
// per route (view = GET, edit = mutating) — retrofitted after the fact, once
// Setup > Users/Roles existed to actually assign those permissions (see
// velvety-sauteeing-breeze.md Sequencing step 6).
// -----------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {

    // GET /api/payment-plans — read by the member form and payment form, so
    // it's open to anyone who works with members or payments (creating and
    // retiring plans stays under setup.manage above).
    Route::get('/payment-plans', [PaymentPlanController::class, 'index'])
        ->middleware('permission:members.view,members.edit,payments.view,payments.edit,setup.manage');

    // Replaces: src/services/memberService.js (all functions)
    Route::prefix('members')->group(function () {

        // GET    /api/members          → listMembers()
        // POST   /api/members          → createMember(input)
        Route::get('/', [MemberController::class, 'index'])->middleware('permission:members.view');
        Route::post('/', [MemberController::class, 'store'])->middleware('permission:members.edit');

        Route::prefix('{member}')->group(function () {
            // GET    /api/members/{id}          → getMember(memberId)
            Route::get('/', [MemberController::class, 'show'])->middleware('permission:members.view');

            // PUT    /api/members/{id}          → updateMember(memberId, updates)
            Route::put('/', [MemberController::class, 'update'])->middleware('permission:members.edit');

            // PATCH  /api/members/{id}/status   → setMemberStatus(memberId, status)
            Route::patch('/status', [MemberController::class, 'updateStatus'])->middleware('permission:members.edit');

            // POST   /api/members/{id}/enroll   → fingerprint/face enrollment on the door device
            Route::post('/enroll', [DeviceEnrollmentController::class, 'member'])->middleware('permission:members.edit');

            // GET    /api/members/{id}/enrollments → which face/fingers are registered on the device
            Route::get('/enrollments', [DeviceEnrollmentController::class, 'memberStatus'])->middleware('permission:members.view');

            // DELETE /api/members/{id}          → deactivateMember(memberId)
            //   (soft-delete: sets status to Inactive, preserves payment history)
            Route::delete('/', [MemberController::class, 'destroy'])->middleware('permission:members.edit');

            // GET    /api/members/{id}/payments  → listTransactionsForMember(memberId)
            //   Also used by: MemberDetailPage.jsx (Payment history tab)
            Route::get('/payments', [MemberController::class, 'payments'])->middleware('permission:members.view');
        });
    });

    // Members > Guests — everyone who signs up, until staff make them a
    // member. See GuestController.
    Route::prefix('guests')->group(function () {
        Route::get('/', [GuestController::class, 'index'])->middleware('permission:members.view');
        Route::get('/{member}', [GuestController::class, 'show'])->middleware('permission:members.view');
        Route::post('/{member}/promote', [GuestController::class, 'promote'])->middleware('permission:members.edit');
        Route::delete('/{member}', [GuestController::class, 'destroy'])->middleware('permission:members.edit');
    });

    // Replaces: src/services/paymentService.js → getPaymentSummary()
    // Used by:  PaymentsDashboardPage.jsx and DashboardPage.jsx
    // NOTE: must be registered BEFORE the prefix('payments')->{payment} group
    // below, otherwise Laravel's router would try to match "summary" as a
    // {payment} route-model-binding id and 404.
    Route::get('/payments/summary', PaymentSummaryController::class)->middleware('permission:payments.view');

    // Replaces: src/services/paymentService.js (listTransactions, recordPayment, etc.)
    // NOTE: this was previously prefix('transactions') — renamed to 'payments'
    // to match the Transaction → Payment model/table rename.
    Route::prefix('payments')->group(function () {

        // GET    /api/payments       → listTransactions()
        // POST   /api/payments       → recordPayment(input)
        Route::get('/', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::post('/', [PaymentController::class, 'store'])->middleware('permission:payments.edit');

        Route::prefix('{payment}')->group(function () {
            // GET    /api/payments/{id}             → getTransaction(transactionId)
            Route::get('/', [PaymentController::class, 'show'])->middleware('permission:payments.view');

            // PUT    /api/payments/{id}/mark-paid   → markTransactionPaid(transactionId)
            Route::put('/mark-paid', [PaymentController::class, 'markPaid'])->middleware('permission:payments.edit');
        });
    });

    // Replaces: src/data/mockData.js → initialAttendance (static data)
    //           + useMemo filter/sort in MemberAttendencePage.jsx
    Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('permission:attendance.view');
    Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('permission:attendance.edit');

    // Staff check-ins from the door device (Members › Staff Attendance).
    Route::get('/staff-attendance', [StaffAttendanceController::class, 'index'])->middleware('permission:attendance.view');
});
