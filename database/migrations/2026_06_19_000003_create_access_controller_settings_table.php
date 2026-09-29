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
        Schema::create('access_controller_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Main Controller');
            $table->string('driver', 40)->default('fake');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->boolean('is_enabled')->default(false)->index();
            $table->text('encrypted_credentials')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_controller_settings');
    }
};
