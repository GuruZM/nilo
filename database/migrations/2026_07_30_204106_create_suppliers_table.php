<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Column names deliberately match `clients` — the shared sheet reads `name`,
     * `address`, `email` and `contact_person` off whatever counterparty it is
     * handed, so matching names means a purchase order renders with no special
     * case in the blade.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tpin')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_person')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
