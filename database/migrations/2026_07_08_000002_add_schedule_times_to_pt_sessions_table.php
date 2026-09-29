<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pt_sessions', function (Blueprint $table) {
            $table->timestamp('scheduled_start_at')->nullable()->after('session_date');
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at');
            $table->index(['trainer_id', 'scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pt_sessions', function (Blueprint $table) {
            $table->dropIndex(['trainer_id', 'scheduled_start_at']);
            $table->dropColumn(['scheduled_start_at', 'scheduled_end_at']);
        });
    }
};
