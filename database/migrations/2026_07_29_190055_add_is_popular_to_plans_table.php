<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which plan the marketing page highlights used to be hardcoded to the
     * "standard" slug, which left admin-created plans unable to take the badge.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('is_active');
        });

        Plan::query()->where('slug', 'standard')->update(['is_popular' => true]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_popular');
        });
    }
};
