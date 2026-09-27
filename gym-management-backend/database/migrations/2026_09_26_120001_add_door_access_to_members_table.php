<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Door access (VFT / ZKTeco device) for members and staff registrations.
 *
 * - member_id_number doubles as the device PIN, so it becomes unique
 *   (format — 1–9 digits — is enforced in the form requests).
 * - access_valid_from/until: the dates the device lets them in, extended by
 *   paid payments (see App\Services\Membership\AccessValidityService).
 * - access_note: why access couldn't be set (e.g. payment with no plan).
 * - device_*: last sync with the device, shown on the member's profile.
 *   device_pin_synced is the PIN currently on the device, so a PIN change
 *   can delete the old device user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unique('member_id_number');
            $table->date('access_valid_from')->nullable()->after('status');
            $table->date('access_valid_until')->nullable()->after('access_valid_from');
            $table->string('access_note')->nullable()->after('access_valid_until');
            // synced | dry_run | failed | no_pin | no_access
            $table->string('device_sync_status')->nullable()->after('access_note');
            $table->text('device_sync_error')->nullable()->after('device_sync_status');
            $table->timestamp('device_synced_at')->nullable()->after('device_sync_error');
            $table->string('device_pin_synced')->nullable()->after('device_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['member_id_number']);
            $table->dropColumn([
                'access_valid_from', 'access_valid_until', 'access_note',
                'device_sync_status', 'device_sync_error', 'device_synced_at', 'device_pin_synced',
            ]);
        });
    }
};
