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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('anon_identity_id')->constrained('anon_identities')->cascadeOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('event_name');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('organization_id');
            $table->index('anon_identity_id');
            $table->index('person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
