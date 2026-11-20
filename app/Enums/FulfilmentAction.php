<?php

namespace App\Enums;

enum FulfilmentAction: string
{
    case Confirm = 'confirm';
    case StartPicking = 'start_picking';
    case ReportShortage = 'report_shortage';
    case ResumePicking = 'resume_picking';
    case Pack = 'pack';
    case MarkReady = 'mark_ready';
    case Dispatch = 'dispatch';
    case OutForDelivery = 'out_for_delivery';
    case RecordFailedAttempt = 'record_failed_attempt';
    case RetryDelivery = 'retry_delivery';
    case ReturnToSender = 'return_to_sender';
    case ConfirmHandover = 'confirm_handover';
    case Deliver = 'deliver';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Confirm => 'Confirm order',
            self::StartPicking => 'Start picking',
            self::ReportShortage => 'Report an unavailable item',
            self::ResumePicking => 'Resume picking',
            self::Pack => 'Confirm picked and packed',
            self::MarkReady => 'Mark ready',
            self::Dispatch => 'Record dispatch',
            self::OutForDelivery => 'Mark out for delivery',
            self::RecordFailedAttempt => 'Record failed attempt',
            self::RetryDelivery => 'Retry delivery',
            self::ReturnToSender => 'Record return to sender',
            self::ConfirmHandover => 'Confirm pickup handover',
            self::Deliver => 'Confirm delivery',
            self::Cancel => 'Cancel order',
        };
    }
}
