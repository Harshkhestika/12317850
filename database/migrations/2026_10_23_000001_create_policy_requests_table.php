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
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_request_id');
            $table->string('agent_id');
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->enum('destination', ['DOMESTIC', 'INTERNATIONAL']);
            $table->date('trip_start');
            $table->date('trip_end');
            $table->enum('plan_code', ['BASIC', 'PLUS', 'PREMIUM']);
            $table->decimal('sum_insured', 12, 2);
            $table->integer('travel_days');
            $table->decimal('premium', 10, 2)->nullable();
            $table->string('status')->default('RECEIVED');
            $table->string('policy_number')->nullable()->unique();
            $table->string('provider_reference')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // CRITICAL: Database-level composite unique constraint to prevent duplicate intake
            $table->unique(['agent_id', 'client_request_id'], 'uq_agent_client_request');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
