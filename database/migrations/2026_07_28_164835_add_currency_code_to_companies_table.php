<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('ZMW')->after('type');
            $table->index('currency_code');
        });

        $this->backfillFromOwners();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['currency_code']);
            $table->dropColumn('currency_code');
        });
    }

    /**
     * Existing companies inherit the currency their owner was working in, so
     * nothing silently flips to the ZMW column default.
     */
    private function backfillFromOwners(): void
    {
        Company::query()
            ->with('owner:id,current_currency_code')
            ->whereHas('owner', function ($query) {
                $query->whereNotNull('current_currency_code');
            })
            ->chunkById(200, function ($companies) {
                foreach ($companies as $company) {
                    $company->forceFill([
                        'currency_code' => strtoupper((string) $company->owner->current_currency_code),
                    ])->saveQuietly();
                }
            });
    }
};
