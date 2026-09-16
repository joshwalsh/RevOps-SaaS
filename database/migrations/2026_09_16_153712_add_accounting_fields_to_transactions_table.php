<?php

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
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('subtotal_cents')->nullable()->after('amount_cents');
            $table->unsignedInteger('tax_cents')->nullable()->after('subtotal_cents');
            $table->unsignedInteger('total_cents')->nullable()->after('tax_cents');
            $table->unsignedInteger('fees_cents')->nullable()->after('total_cents');
            $table->unsignedInteger('discount_cents')->nullable()->after('fees_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['subtotal_cents', 'tax_cents', 'total_cents', 'fees_cents', 'discount_cents']);
        });
    }
};
