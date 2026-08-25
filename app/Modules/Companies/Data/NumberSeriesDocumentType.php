<?php

namespace App\Modules\Companies\Data;

enum NumberSeriesDocumentType: string
{
    case Quote = 'QUOTE';
    case Invoice = 'INVOICE';

    public function key(): string
    {
        return match ($this) {
            self::Quote => 'quote',
            self::Invoice => 'invoice',
        };
    }

    public function defaultPattern(): string
    {
        return match ($this) {
            self::Quote => 'Q-{YEAR}-{NUMBER}',
            self::Invoice => 'I-{YEAR}-{NUMBER}',
        };
    }
}
