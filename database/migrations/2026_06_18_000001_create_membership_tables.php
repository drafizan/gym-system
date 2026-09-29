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
        Schema::create('membership_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('duration_days');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_walk_in')->default(false);
            $table->boolean('access_allowed')->default(true);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('member_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_package_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 32)->default('active')->index();
            $table->string('payment_status', 32)->default('paid');
            $table->decimal('amount', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('access_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('status', 32)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->text('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_sync_logs');
        Schema::dropIfExists('member_memberships');
        Schema::dropIfExists('membership_packages');
    }
};
