<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Documents\Data\DocumentSourceFailure;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;

final class ApplyDocumentDraftSources
{
    public function handle(
        Document $document,
        ?string $currencyCode,
        ?string $documentLanguage,
        ?string $bankAccountId,
        ?string $termsAndConditions,
        ?string $notes,
        LockedDocumentConfiguration $configuration,
    ): void {
        $bankSnapshot = DocumentBankSnapshot::query()
            ->where('document_id', $document->id)
            ->lockForUpdate()
            ->first();
        $currencyChanged = $currencyCode !== $document->currency_code;
        $bankChanged = $bankAccountId !== $bankSnapshot?->bank_account_id;

        if ($currencyChanged) {
            $currency = $currencyCode === null
                ? null
                : $configuration->currencies
                    ->where('active', true)
                    ->firstWhere('currency_code', $currencyCode);

            if ($currencyCode !== null && ! $currency instanceof CompanyCurrency) {
                throw DocumentSourceFailure::currencyUnavailable();
            }

            $document->fill([
                'currency_code' => $currency?->currency_code,
                'currency_precision' => $currency?->currency_precision,
            ]);
        }

        $document->fill([
            'document_language' => $documentLanguage,
            'terms_and_conditions' => $termsAndConditions,
            'notes' => $notes,
        ]);

        if (! $bankChanged) {
            return;
        }

        $bankSnapshot?->delete();

        if ($bankAccountId === null) {
            return;
        }

        $bankAccount = $configuration->bankAccounts
            ->whereNull('archived_at')
            ->firstWhere('id', $bankAccountId);

        if (! $bankAccount instanceof BankAccount) {
            throw DocumentSourceFailure::bankUnavailable();
        }

        $bankCurrency = $bankAccount->currency_id === null
            ? null
            : $configuration->currencies->firstWhere('id', $bankAccount->currency_id);

        DocumentBankSnapshot::query()->create([
            'document_id' => $document->id,
            'bank_account_id' => $bankAccount->id,
            'label' => $bankAccount->label,
            'bank_name' => $bankAccount->bank_name,
            'account_holder' => $bankAccount->account_holder,
            'account_number' => $bankAccount->account_number,
            'swift_bic' => $bankAccount->swift_bic,
            'currency_code' => $bankCurrency?->currency_code,
            'local_routing_details' => $bankAccount->local_routing_details,
        ]);
    }
}
