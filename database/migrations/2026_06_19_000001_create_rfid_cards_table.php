<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('card_number');
            $table->string('status', 32)->default('active')->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('rfid_cards')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['card_number', 'status']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX rfid_cards_active_member_unique ON rfid_cards (member_id) WHERE status = 'active'");
            DB::statement("CREATE UNIQUE INDEX rfid_cards_active_card_number_unique ON rfid_cards (card_number) WHERE status = 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS rfid_cards_active_card_number_unique');
            DB::statement('DROP INDEX IF EXISTS rfid_cards_active_member_unique');
        }

        Schema::dropIfExists('rfid_cards');
    }
};
