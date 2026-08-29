<?php

namespace App\Modules\Delivery\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\RenderedEmailTemplate;
use App\Modules\Delivery\Rules\EmailTemplatePlaceholders;
use App\Modules\Delivery\Support\OutwardDocumentFormatter;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Models\InvoiceTransaction;

final readonly class PaymentReceivedDeliveryComposer
{
    public function __construct(
        private CurrentDocumentRepresentation $representation,
        private ResolveCompanyEmailTemplate $templates,
        private EmailTemplatePlaceholders $placeholders,
        private OutwardDocumentFormatter $format,
    ) {}

    public function for(
        Company $company,
        Document $document,
        InvoiceTransaction $payment,
        InvoiceLedger $ledger,
        string $publicUrl,
    ): RenderedEmailTemplate {
        $outward = $this->representation->publicInvoice($company, $document);
        $language = $outward->language;
        $template = $this->templates->for(EmailTemplateEvent::PaymentReceived, $language)['template'];
        $snapshot = DocumentCompanySnapshot::query()
            ->where('document_id', $document->id)->firstOrFail();
        $precision = $document->currency_precision ?? 0;
        $currency = (string) $document->currency_code;

        return $this->placeholders->render($template, [
            'company_name' => $outward->company['displayName'],
            'customer_name' => $outward->customer['displayName'] ?? '',
            'document_number' => $outward->number,
            'document_total' => $outward->total,
            'public_url' => $publicUrl,
            'due_date' => $outward->dueDate ?? '',
            'outstanding_amount' => $this->format->money(
                (string) DecimalRules::exactMoney(
                    $ledger->outstanding($document->total),
                    $precision,
                ),
                $precision,
                $currency,
                $snapshot->currency_display_style,
                $language,
            ),
            'payment_amount' => $this->format->money(
                $payment->amount,
                $precision,
                $currency,
                $snapshot->currency_display_style,
                $language,
            ),
            'payment_date' => $this->format->date($payment->transaction_date, $language) ?? '',
        ], (string) trans('document_emails.preview.unavailable', locale: $language));
    }
}
