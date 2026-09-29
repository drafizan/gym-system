<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt_trainers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('specialization')->nullable();
            $table->decimal('commission_per_session', 10, 2)->default(30);
            $table->date('joined_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pt_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sessions_count');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('commission_per_session', 10, 2)->default(30);
            $table->unsignedInteger('validity_days')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('pt_member_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pt_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('used_sessions')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->date('purchased_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });

        Schema::create('pt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pt_member_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('pt_trainers')->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->string('status', 32)->default('completed')->index();
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trainer_id', 'session_date']);
            $table->index(['member_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_sessions');
        Schema::dropIfExists('pt_member_packages');
        Schema::dropIfExists('pt_packages');
        Schema::dropIfExists('pt_trainers');
    }
};
