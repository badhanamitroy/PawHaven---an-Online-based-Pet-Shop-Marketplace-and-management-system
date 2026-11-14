<?php

namespace App\Enums;

enum ListingModerationActionType: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Removed = 'removed';
}
