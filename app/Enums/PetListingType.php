<?php

namespace App\Enums;

enum PetListingType: string
{
    case Sale = 'sale';
    case Adoption = 'adoption';
    case Rescue = 'rescue';
    case LostFound = 'lost_found';
    case Rehoming = 'rehoming';
}
