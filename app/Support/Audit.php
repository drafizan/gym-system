<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class Audit
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(
        Request $request,
        string $module,
        string $action,
        ?string $recordType = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'module' => $module,
            'action' => $action,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'old_values' => self::filterSensitive($oldValues),
            'new_values' => self::filterSensitive($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private static function filterSensitive(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $blockedKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'remember_token',
            'token',
            'secret',
            'deployment_key',
            'encrypted_credentials',
        ];

        foreach ($values as $key => $value) {
            if (in_array($key, $blockedKeys, true)) {
                unset($values[$key]);

                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::filterSensitive($value);
            }
        }

        return $values;
    }
}
