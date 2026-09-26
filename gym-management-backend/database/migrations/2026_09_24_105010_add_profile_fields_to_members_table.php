<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the extended member-profile fields requested for MemberFormPage.jsx:
 * an external member ID card number, NIC, WhatsApp number, body metrics
 * (stored canonically as kg/cm regardless of which unit staff entered them
 * in), occupation, and login credentials (username/password) for a future
 * member portal.
 *
 * All new columns are nullable at the DB level even though several are
 * required by StoreMemberRequest for new members — existing seeded rows
 * have none of this data yet, and a NOT NULL column with no sensible
 * default would break them. "Required" for these is enforced at the
 * validation layer only, same as full_name/email/phone already work for
 * pre-existing rows created before those became mandatory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('member_id_number')->nullable()->after('id');
            $table->string('nic')->nullable()->after('full_name');
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('address');
            $table->decimal('height_cm', 5, 2)->nullable()->after('weight_kg');
            $table->string('occupation')->nullable()->after('height_cm');
            $table->string('username')->nullable()->unique()->after('emergency_contact_phone');
            $table->string('password')->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'member_id_number',
                'nic',
                'whatsapp_number',
                'weight_kg',
                'height_cm',
                'occupation',
                'username',
                'password',
            ]);
        });
    }
};
