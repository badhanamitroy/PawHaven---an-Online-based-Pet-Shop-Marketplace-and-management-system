<?php

namespace App\Enums;

enum PetListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Completed = 'completed';
    case Removed = 'removed';
}
