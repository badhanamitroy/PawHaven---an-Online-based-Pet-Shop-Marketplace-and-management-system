<?php

namespace App\Enums;

enum PaymentSource: string
{
    case PointOfSale = 'pos';
    case Online = 'online';
    case AdminManual = 'admin_manual';
    case Gateway = 'gateway';
}
