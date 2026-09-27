<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * members.email became optional (2026_09_24_105317), but attendance still
 * required it, so recording attendance for a member without an email
 * failed. Needed now that door punches create attendance automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
