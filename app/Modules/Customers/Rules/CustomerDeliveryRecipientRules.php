<?php

namespace App\Modules\Customers\Rules;

use App\Modules\Customers\Data\CustomerDeliveryRecipientData;
use App\Modules\Customers\Exceptions\CustomerContactException;
use App\Modules\Customers\Exceptions\CustomerDeliveryException;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use Illuminate\Support\Collection;

final class CustomerDeliveryRecipientRules
{
    /**
     * @param  Collection<int, CustomerContact>  $contacts
     * @param  list<CustomerDeliveryRecipientData>  $recipients
     */
    public function assertDeliveryValid(Collection $contacts, array $recipients): void
    {
        $contactsById = $contacts->keyBy('id');
        $emails = [];

        foreach ($recipients as $recipient) {
            if ($recipient->contactId !== null) {
                $contact = $contactsById->get($recipient->contactId);

                if (! $contact instanceof CustomerContact || $contact->archived_at !== null || $contact->email === null) {
                    throw CustomerDeliveryException::invalidContact();
                }

                $email = $contact->email;
            } else {
                $email = (string) $recipient->explicitEmail;
            }

            $normalized = mb_strtolower($email);

            if (isset($emails[$normalized])) {
                throw CustomerDeliveryException::duplicateRecipient();
            }

            $emails[$normalized] = true;
        }
    }

    /**
     * @param  Collection<int, CustomerDeliveryRecipient>  $recipients
     * @param  Collection<int, CustomerContact>  $contacts
     */
    public function assertContactChangeValid(
        CustomerContact $contact,
        ?string $newEmail,
        Collection $recipients,
        Collection $contacts,
    ): void {
        $referenced = $recipients->contains(
            fn (CustomerDeliveryRecipient $recipient): bool => $recipient->contact_id === $contact->id,
        );

        if ($referenced && $newEmail === null) {
            throw CustomerContactException::recipientDependency();
        }

        if (! $referenced) {
            return;
        }

        $contactsById = $contacts->keyBy('id');
        $emails = [];

        foreach ($recipients as $recipient) {
            $email = $recipient->contact_id === null
                ? $recipient->explicit_email
                : ($recipient->contact_id === $contact->id
                    ? $newEmail
                    : $contactsById->get($recipient->contact_id)?->email);

            if ($email === null) {
                throw CustomerContactException::recipientDependency();
            }

            $normalized = mb_strtolower($email);

            if (isset($emails[$normalized])) {
                throw CustomerContactException::duplicateRecipient();
            }

            $emails[$normalized] = true;
        }
    }
}
