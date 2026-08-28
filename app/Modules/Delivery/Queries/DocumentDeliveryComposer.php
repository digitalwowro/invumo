<?php

namespace App\Modules\Delivery\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\OutwardDocument;
use App\Modules\Delivery\Rules\EmailTemplatePlaceholders;
use App\Modules\Delivery\Support\OutwardDocumentFormatter;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Transactions\Queries\InvoiceTransactionsForInvoice;

final readonly class DocumentDeliveryComposer
{
    private const PENDING_PUBLIC_URL = '{{public_url}}';

    public function __construct(
        private CurrentDocumentRepresentation $representation,
        private ResolveCompanyEmailTemplate $templates,
        private EmailTemplatePlaceholders $placeholders,
        private InvoiceTransactionsForInvoice $transactions,
        private OutwardDocumentFormatter $format,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, string $documentId, DocumentKind $kind): array
    {
        $document = Document::query()->whereKey($documentId)->where('kind', $kind)->firstOrFail();
        $outward = $kind === DocumentKind::Quote
            ? $this->representation->forQuote($company, $actor, $documentId)
            : $this->representation->forInvoice($company, $actor, $documentId);
        $event = $kind === DocumentKind::Quote
            ? EmailTemplateEvent::QuoteSent
            : EmailTemplateEvent::InvoiceSent;
        /** @var CompanyEmailTemplateData $template */
        $template = $this->templates->for($event, $outward->language)['template'];
        $rendered = $this->placeholders->render(
            $template,
            $this->values($document, $outward, $kind),
            (string) trans('document_emails.preview.unavailable', locale: $outward->language),
        );
        $recipients = DocumentDeliveryRecipient::query()
            ->where('document_id', $documentId)->orderBy('display_order')->get();

        return [
            'deliveryKey' => (string) str()->uuid7(),
            'sendUrl' => route(
                $kind === DocumentKind::Quote ? 'quotes.deliveries.store' : 'invoices.deliveries.store',
                [$company, $documentId],
                false,
            ),
            'editVersion' => $document->edit_version,
            'language' => $outward->language,
            'attachmentMode' => $document->deliverySetting()->firstOrFail()->email_attachment_mode->value,
            'recipients' => $recipients->map(fn (DocumentDeliveryRecipient $recipient): array => [
                'role' => $recipient->role->value,
                'name' => $recipient->name,
                'email' => $recipient->email,
            ])->values()->all(),
            'subject' => $rendered->subject,
            'body' => $rendered->body,
            'buttonLabel' => $rendered->buttonLabel,
            'signature' => $rendered->signature,
            'requiresFinalStateConfirmation' => $kind === DocumentKind::Quote
                && in_array(Quote::query()->whereKey($documentId)->value('lifecycle'), ['ACCEPTED', 'REJECTED'], true),
        ];
    }

    /** @return array<string, string> */
    private function values(Document $document, OutwardDocument $outward, DocumentKind $kind): array
    {
        $values = [
            'company_name' => $outward->company['displayName'],
            'customer_name' => $outward->customer['displayName'] ?? (string) trans(
                'document_emails.preview.unavailable',
                locale: $outward->language,
            ),
            'document_number' => $outward->number,
            'document_total' => $outward->total,
            'public_url' => self::PENDING_PUBLIC_URL,
            'valid_until' => $outward->validUntil ?? '',
            'due_date' => $outward->dueDate ?? '',
            'outstanding_amount' => $outward->total,
        ];

        if ($kind === DocumentKind::Invoice && $document->currency_code !== null) {
            $company = DocumentCompanySnapshot::query()->where('document_id', $document->id)->firstOrFail();
            $outstanding = $this->transactions->ledger($document->id)->outstanding($document->total);
            $values['outstanding_amount'] = $this->format->money(
                (string) DecimalRules::exactMoney($outstanding, $document->currency_precision ?? 0),
                $document->currency_precision ?? 0,
                $document->currency_code,
                $company->currency_display_style,
                $outward->language,
            );
        }

        return $values;
    }
}
