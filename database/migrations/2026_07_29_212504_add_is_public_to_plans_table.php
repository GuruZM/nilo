<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `is_active` used to serve double duty as both "sellable" and "listed",
     * leaving no way to hold a plan that admins can assign but nobody can buy.
     * Complimentary plans need exactly that, so listing gets its own flag.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
