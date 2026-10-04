<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pt_sessions', function (Blueprint $table) {
            $table->uuid('check_in_token')->nullable();
            $table->unique(['check_in_token', 'pt_member_package_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pt_sessions', function (Blueprint $table) {
            $table->dropUnique(['check_in_token', 'pt_member_package_id']);
            $table->dropColumn('check_in_token');
        });
    }
};
