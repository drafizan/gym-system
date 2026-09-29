<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SystemSettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return collect($this->defaults())
            ->mapWithKeys(fn (mixed $default, string $key): array => [$key => $this->get($key)])
            ->all();
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return $this->defaults()[$key] ?? $fallback;
        }

        if ($setting->is_encrypted) {
            return json_decode(Crypt::decryptString((string) ($setting->value['payload'] ?? '')), true);
        }

        return $setting->value['payload'] ?? $fallback;
    }

    public function set(string $key, mixed $value, bool $encrypted = false): Setting
    {
        $this->validate($key, $value);

        return Setting::query()->updateOrCreate([
            'key' => $key,
        ], [
            'value' => [
                'payload' => $encrypted ? Crypt::encryptString(json_encode($value)) : $value,
            ],
            'is_encrypted' => $encrypted,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $this->isSensitive($key));
        }
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * @return array<int, string>
     */
    public function paymentMethods(): array
    {
        $enabled = $this->get('payment_methods', []);
        $allowed = PaymentMethod::values();
        $filtered = array_values(array_intersect($enabled, $allowed));

        return $filtered === [] ? $allowed : $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'gym_name' => 'Gorilla Mutantz Gym Sdn Bhd',
            'company_name' => 'Gorilla Mutantz Gym Sdn Bhd',
            'gym_address' => '',
            'gym_contact_number' => '+601128520309',
            'receipt_footer' => 'Thank you for training with Gorilla Mutantz Gym.',
            'expiring_soon_days' => config('gym.members.expiring_soon_days', 7),
            'backup_path' => config('gym.backup.path'),
            'backup_schedule' => 'daily',
            'backup_retention_count' => config('gym.backup.retention_count', 30),
            'backup_os' => 'macos',
            'backup.pg_dump_binary' => config('gym.backup.pg_dump_binary', 'pg_dump'),
            'backup.psql_binary' => config('gym.backup.psql_binary', 'psql'),
            'payment_methods' => PaymentMethod::values(),
            'default_membership_packages' => [
                'Walk-in Citizen',
                'Walk-in Student',
                'Walk-in Senior Citizen',
                'Walk-in OKU',
                'Monthly Citizen',
                'Monthly Student',
                'Monthly Senior Citizen',
                'Monthly Special',
                'First-Time Registration Citizen',
                'First-Time Registration Student',
                'First-Time Registration Senior Citizen',
                'First-Time Registration Special',
                'Registration Fee',
            ],
            'access_controller' => [
                'driver' => 'fake',
                'setting_id' => null,
            ],
        ];
    }

    public function isSensitive(string $key): bool
    {
        return in_array($key, [
            'access_controller.credentials',
            'deployment.secret',
            'backup.remote_credentials',
        ], true);
    }

    private function validate(string $key, mixed $value): void
    {
        $rules = match ($key) {
            'gym_name' => [$key => ['required', 'string', 'max:120']],
            'company_name' => [$key => ['required', 'string', 'max:160']],
            'gym_address' => [$key => ['nullable', 'string', 'max:500']],
            'gym_contact_number' => [$key => ['required', 'string', 'max:40']],
            'receipt_footer' => [$key => ['nullable', 'string', 'max:500']],
            'expiring_soon_days' => [$key => ['required', 'integer', 'min:1', 'max:90']],
            'backup_path' => [$key => ['required', 'string', 'max:500']],
            'backup_schedule' => [$key => ['required', 'in:daily,weekly,manual']],
            'backup_retention_count' => [$key => ['required', 'integer', 'min:1', 'max:365']],
            'backup_os' => [$key => ['required', 'in:windows,linux,macos,custom']],
            'backup.pg_dump_binary' => [$key => ['required', 'string', 'max:500']],
            'backup.psql_binary' => [$key => ['required', 'string', 'max:500']],
            'payment_methods' => [
                $key => ['required', 'array', 'min:1'],
                $key.'.*' => ['string', 'in:'.implode(',', PaymentMethod::values())],
            ],
            'default_membership_packages' => [
                $key => ['required', 'array', 'min:1'],
                $key.'.*' => ['string', 'max:120'],
            ],
            'access_controller' => [
                $key => ['required', 'array'],
                $key.'.driver' => ['required', 'string', 'max:40'],
                $key.'.setting_id' => ['nullable', 'integer'],
            ],
            'access_controller.credentials' => [$key => ['required', 'array']],
            'deployment.secret' => [$key => ['required', 'string', 'min:16']],
            'backup.remote_credentials' => [$key => ['required', 'array']],
            default => [$key => ['nullable']],
        };

        $payload = str_contains($key, '.')
            ? $this->undot($key, $value)
            : [$key => $value];

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function undot(string $key, mixed $value): array
    {
        $payload = [];
        data_set($payload, $key, $value);

        return $payload;
    }
}
