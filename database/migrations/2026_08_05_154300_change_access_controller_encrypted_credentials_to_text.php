<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('access_controller_settings')) {
            return;
        }

        $existingCredentials = DB::table('access_controller_settings')
            ->select('id', 'encrypted_credentials')
            ->whereNotNull('encrypted_credentials')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->id => $this->decodeJsonCredentials($row->encrypted_credentials)]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE access_controller_settings ALTER COLUMN encrypted_credentials TYPE TEXT USING NULL');
        }

        foreach ($existingCredentials as $id => $credentials) {
            if ($credentials === null) {
                continue;
            }

            DB::table('access_controller_settings')
                ->where('id', $id)
                ->update([
                    'encrypted_credentials' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('access_controller_settings') && DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE access_controller_settings ALTER COLUMN encrypted_credentials TYPE JSON USING NULL');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonCredentials(mixed $credentials): ?array
    {
        if (is_array($credentials)) {
            return $credentials;
        }

        if (is_object($credentials)) {
            return (array) $credentials;
        }

        if (! is_string($credentials) || trim($credentials) === '') {
            return null;
        }

        $decoded = json_decode($credentials, true);

        return is_array($decoded) ? $decoded : null;
    }
};
