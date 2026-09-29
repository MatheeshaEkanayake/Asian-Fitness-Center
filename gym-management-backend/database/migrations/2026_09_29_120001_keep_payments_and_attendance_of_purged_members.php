<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments and attendance outlive a purged member (members:purge-archived):
 * member_id is cleared instead of cascading the delete, and payments keep a
 * copy of who paid so payment details still show them. Attendance already
 * keeps member_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->foreignId('member_id')->nullable()->change();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();

            $table->string('member_name')->nullable()->after('member_id');
            $table->string('member_id_number')->nullable()->after('member_name');
            $table->string('member_phone')->nullable()->after('member_id_number');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->foreignId('member_id')->nullable()->change();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['member_name', 'member_id_number', 'member_phone']);
            $table->dropForeign(['member_id']);
            $table->foreignId('member_id')->nullable(false)->change();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->foreignId('member_id')->nullable(false)->change();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }
};
