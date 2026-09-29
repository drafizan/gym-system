<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qr = 'qr';
    case DebitCreditCard = 'debit_credit_card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Qr => 'QR Pay',
            self::DebitCreditCard => 'Debit/Credit Card',
        };
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? str((string) $value)->headline()->toString();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $method): string => $method->value, self::cases());
    }
}
