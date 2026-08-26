<?php

namespace App\Modules\Quotes\Data;

use Carbon\CarbonImmutable;

enum QuoteDisplayStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';

    public static function resolve(
        QuoteLifecycle $lifecycle,
        ?CarbonImmutable $validUntil,
        CarbonImmutable $companyLocalDate,
    ): self {
        if ($lifecycle === QuoteLifecycle::Accepted) {
            return self::Accepted;
        }

        if ($lifecycle === QuoteLifecycle::Rejected) {
            return self::Rejected;
        }

        if ($validUntil !== null && $companyLocalDate->greaterThan($validUntil)) {
            return self::Expired;
        }

        return self::from($lifecycle->value);
    }
}
