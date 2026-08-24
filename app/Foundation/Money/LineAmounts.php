<?php

declare(strict_types=1);

namespace App\Foundation\Money;

final readonly class LineAmounts
{
    public function __construct(
        public string $itemsSubtotal,
        public string $itemsTotal,
        public string $discountAmount,
        public string $grandSubtotal,
        public string $taxAmount,
        public string $finalLineTotal,
    ) {}

    /**
     * @return array{
     *     items_subtotal: string,
     *     items_total: string,
     *     discount_amount: string,
     *     grand_subtotal: string,
     *     tax_amount: string,
     *     final_line_total: string
     * }
     */
    public function toArray(): array
    {
        return [
            'items_subtotal' => $this->itemsSubtotal,
            'items_total' => $this->itemsTotal,
            'discount_amount' => $this->discountAmount,
            'grand_subtotal' => $this->grandSubtotal,
            'tax_amount' => $this->taxAmount,
            'final_line_total' => $this->finalLineTotal,
        ];
    }
}
