<?php

namespace App\Support;

use App\Models\MembershipPackage;
use Illuminate\Http\Request;

class RegistrationCheckout
{
    public static function fee(): float
    {
        return (float) (MembershipPackage::query()->where('name', 'Registration Fee')->value('price') ?? 60);
    }

    public static function draft(Request $request, ?string $token): array
    {
        if (! $token) {
            return [];
        }

        $draft = $request->session()->get('registration_checkouts.'.$token);
        abort_unless(is_array($draft), 404, 'Registration checkout has expired.');

        return $draft;
    }
}
