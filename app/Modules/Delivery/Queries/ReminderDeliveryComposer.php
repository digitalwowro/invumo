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

final readonly class ReminderDeliveryComposer
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
        InvoiceLedger $ledger,
        string $publicUrl,
    ): RenderedEmailTemplate {
        $outward = $this->representation->publicInvoice($company, $document);
        $language = $outward->language;
        $template = $this->templates->for(EmailTemplateEvent::PaymentReminder, $language)['template'];
        $snapshot = DocumentCompanySnapshot::query()
            ->where('document_id', $document->id)->firstOrFail();
        $outstanding = $ledger->outstanding($document->total);

        return $this->placeholders->render($template, [
            'company_name' => $outward->company['displayName'],
            'customer_name' => $outward->customer['displayName'] ?? '',
            'document_number' => $outward->number,
            'document_total' => $outward->total,
            'public_url' => $publicUrl,
            'due_date' => $outward->dueDate ?? '',
            'outstanding_amount' => $this->format->money(
                (string) DecimalRules::exactMoney($outstanding, $document->currency_precision ?? 0),
                $document->currency_precision ?? 0,
                (string) $document->currency_code,
                $snapshot->currency_display_style,
                $language,
            ),
        ], (string) trans('document_emails.preview.unavailable', locale: $language));
    }
}
