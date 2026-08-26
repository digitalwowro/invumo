<!DOCTYPE html>
<html lang="{{ $document['language'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document['kind'] }} {{ $document['number'] }}</title>
    <style>
        @font-face {
            font-family: 'Atkinson Hyperlegible Next';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ $fontRegular }}') format('truetype');
        }
        @font-face {
            font-family: 'Atkinson Hyperlegible Next';
            font-style: normal;
            font-weight: 700;
            src: url('file://{{ $fontBold }}') format('truetype');
        }
        @font-face {
            font-family: 'Atkinson Hyperlegible Mono';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ $fontMono }}') format('truetype');
        }
        @font-face {
            font-family: 'Atkinson Hyperlegible Mono';
            font-style: normal;
            font-weight: 700;
            src: url('file://{{ $fontMonoBold }}') format('truetype');
        }
        {!! file_get_contents(resource_path('css/outward-document.css')) !!}
    </style>
</head>
<body style="--document-accent: {{ $document['theme']['accentColor'] }}; --document-on-accent: {{ $document['theme']['onAccentColor'] }}; --document-text: {{ $document['theme']['textColor'] }}; --document-rule: {{ $document['theme']['ruleColor'] }};">
    <div class="document-accent"></div>
    <table class="document-header">
        <tr>
            <td>
                @if ($logoDataUri)
                    <img class="document-logo" src="{{ $logoDataUri }}" alt="">
                @endif
                <p class="document-company-name">{{ $document['company']['displayName'] }}</p>
                @if ($document['company']['legalName'])
                    <p class="document-detail">{{ $document['company']['legalName'] }}</p>
                @endif
            </td>
            <td class="document-right">
                <h1 class="document-title">{{ $document['kind'] }}</h1>
                <span class="document-number">{{ $document['number'] }}</span>
                <div class="document-meta">
                    @if ($document['issueDate'])
                        <p class="document-meta-row"><span class="document-meta-label">{{ $document['labels']['issue_date'] }}</span> {{ $document['issueDate'] }}</p>
                    @endif
                    @if ($document['validUntil'])
                        <p class="document-meta-row"><span class="document-meta-label">{{ $document['labels']['valid_until'] }}</span> {{ $document['validUntil'] }}</p>
                    @endif
                    @if ($document['dueDate'])
                        <p class="document-meta-row"><span class="document-meta-label">{{ $document['labels']['due_date'] }}</span> {{ $document['dueDate'] }}</p>
                    @endif
                    @if ($document['customerReference'])
                        <p class="document-meta-row"><span class="document-meta-label">{{ $document['labels']['customer_reference'] }}</span> {{ $document['customerReference'] }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="document-parties">
        <tr>
            <td class="document-party">
                <span class="document-label">{{ $document['labels']['from'] }}</span>
                @foreach ([...$document['company']['address'], ...$document['company']['registrations'], ...$document['company']['contacts']] as $detail)
                    <p class="document-detail">{{ $detail }}</p>
                @endforeach
            </td>
            <td class="document-party">
                <span class="document-label">{{ $document['labels']['bill_to'] }}</span>
                @if ($document['customer'])
                    <p class="document-party-name">{{ $document['customer']['displayName'] }}</p>
                    @foreach ([...$document['customer']['contact'], ...$document['customer']['address'], ...$document['customer']['registrations'], ...$document['customer']['contacts']] as $detail)
                        <p class="document-detail">{{ $detail }}</p>
                    @endforeach
                @else
                    <p class="document-detail">{{ $document['labels']['not_set'] }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="document-lines">
        <thead>
            <tr>
                <th class="line-description">{{ $document['labels']['description'] }}</th>
                <th class="line-quantity">{{ $document['labels']['quantity'] }}</th>
                <th class="line-price document-right">{{ $document['labels']['unit_price'] }}</th>
                <th class="line-tax">{{ $document['labels']['tax'] }}</th>
                <th class="line-total document-right">{{ $document['labels']['line_total'] }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($document['lines'] as $line)
                <tr>
                    <td class="line-description">
                        <span class="document-copy">{{ $line['description'] }}</span>
                        @if ($line['discount'])
                            <p class="document-detail">{{ $document['labels']['discount'] }}: {{ $line['discount'] }}</p>
                        @endif
                    </td>
                    <td class="line-quantity document-data">{{ $line['quantity'] }}</td>
                    <td class="line-price document-data document-right">{{ $line['unitPrice'] }}</td>
                    <td class="line-tax">{{ $line['tax'] ?? $document['labels']['not_set'] }}</td>
                    <td class="line-total document-data document-right">{{ $line['total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="document-muted">{{ $document['labels']['no_lines'] }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="document-summary">
        <tr>
            <td class="document-summary-spacer"></td>
            <td class="document-summary-values">
                <div class="document-summary-row">{{ $document['labels']['subtotal'] }} <span class="document-data document-right">{{ $document['subtotal'] }}</span></div>
                <div class="document-summary-row">{{ $document['labels']['tax_total'] }} <span class="document-data document-right">{{ $document['taxTotal'] }}</span></div>
                <div class="document-summary-total">{{ $document['labels']['total'] }} <span class="document-data document-right">{{ $document['total'] }}</span></div>
            </td>
        </tr>
    </table>

    @if ($document['bank'] || $document['notes'])
        <table class="document-section-grid"><tr>
            @if ($document['bank'])
                <td class="document-section">
                    <h2 class="document-section-title">{{ $document['labels']['bank_details'] }}</h2>
                    @foreach ($document['bank'] as $row)
                        <p class="document-bank-row"><span class="document-bank-label">{{ $row['label'] }}:</span> <span class="document-data">{{ $row['value'] }}</span></p>
                    @endforeach
                </td>
            @endif
            @if ($document['notes'])
                <td class="document-section">
                    <h2 class="document-section-title">{{ $document['labels']['notes'] }}</h2>
                    <div class="document-copy">{{ $document['notes'] }}</div>
                </td>
            @endif
        </tr></table>
    @endif

    @if ($document['termsAndConditions'])
        <table class="document-section-grid"><tr><td class="document-section">
            <h2 class="document-section-title">{{ $document['labels']['terms_and_conditions'] }}</h2>
            <div class="document-copy">{{ $document['termsAndConditions'] }}</div>
        </td></tr></table>
    @endif
</body>
</html>
