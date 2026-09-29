<?php

namespace App\Enums;

enum SaleType: string
{
    case MembershipSale = 'membership_sale';
    case MembershipRenewal = 'membership_renewal';
    case ProductSale = 'product_sale';
    case PtSession = 'pt_session';
    case WalkInSale = 'walk_in_sale';

    public function label(): string
    {
        return match ($this) {
            self::MembershipSale => 'Membership Sale',
            self::MembershipRenewal => 'Membership Renewal',
            self::ProductSale => 'Product Sale',
            self::PtSession => 'PT Session',
            self::WalkInSale => 'Walk In Sale',
        };
    }
}
