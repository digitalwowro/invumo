<?php

namespace App\Modules\Delivery\Data;

enum ProviderWebhookEventType: string
{
    case Delivered = 'DELIVERED';
    case SoftBounced = 'SOFT_BOUNCED';
    case HardBounced = 'HARD_BOUNCED';
    case Opened = 'OPENED';
    case Clicked = 'CLICKED';
    case FeedbackLoop = 'FEEDBACK_LOOP';

    public function milestoneColumn(): string
    {
        return match ($this) {
            self::Delivered => 'delivered_at',
            self::SoftBounced => 'soft_bounced_at',
            self::HardBounced => 'hard_bounced_at',
            self::Opened => 'opened_at',
            self::Clicked => 'clicked_at',
            self::FeedbackLoop => 'feedback_loop_at',
        };
    }
}
