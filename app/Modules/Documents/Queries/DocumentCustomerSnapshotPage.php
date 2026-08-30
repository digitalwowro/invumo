<?php

namespace App\Modules\Documents\Queries;

use App\Modules\Documents\Models\DocumentCustomerSnapshot;

final readonly class DocumentCustomerSnapshotPage
{
    /** @return array<string, string|null>|null */
    public function for(string $documentId): ?array
    {
        $snapshot = DocumentCustomerSnapshot::query()
            ->where('document_id', $documentId)
            ->first();

        if ($snapshot === null) {
            return null;
        }

        return [
            'type' => $snapshot->type->value,
            ...$snapshot->only([
                'first_name',
                'last_name',
                'legal_name',
                'contact_name',
                'contact_position_title',
                'email',
                'phone',
                'address_line_1',
                'address_line_2',
                'city',
                'region',
                'postal_code',
                'country_code',
                'tax_registration_label',
                'tax_registration_identifier',
                'business_registration_label',
                'business_registration_number',
            ]),
        ];
    }
}
