<?php

namespace App\Enums;

enum RfidCardStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Lost = 'lost';
    case Blocked = 'blocked';
}
