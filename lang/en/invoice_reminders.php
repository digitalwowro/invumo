<?php

return ['failures' => [
    'generic' => 'The reminder could not be completed.',
    'schedule_changed' => 'The schedule changed before this reminder ran.',
    'invoice_cancelled' => 'The Invoice was cancelled.',
    'nothing_outstanding' => 'The Invoice has no outstanding balance.',
    'stale_before_due' => 'The before-due reminder became stale.',
    'newer_after_due' => 'A newer after-due reminder replaced this one.',
    'schedule_out_of_range' => 'The calculated reminder date is outside the supported range.',
    'public_access_disabled' => 'Secure public access is disabled for this Invoice.',
    'recipients_unavailable' => 'No valid primary recipient is available.',
    'reminder_job_failed' => 'The reminder worker exhausted its retries.',
    'sender_access_unavailable' => 'The Company or Account can no longer send email.',
    'public_link_unavailable' => 'The secure public link is no longer available.',
    'reminder_no_longer_eligible' => 'The Invoice no longer qualifies for this reminder.',
    'interrupted_submission' => 'Delivery may have occurred, but the provider outcome is unknown.',
    'ambiguous_transmission' => 'Delivery may have occurred, but the provider outcome is unknown.',
    'sending_quota_exceeded' => 'The shared sending limit has been reached.',
    'internal_delivery_failure' => 'The delivery worker could not complete this reminder.',
]];
