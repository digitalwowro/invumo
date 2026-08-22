# Calculation, Decimal Precision, and Rounding

Status: Approved architecture decision
Last updated: 2026-08-22

Invumo uses one deterministic calculation model for quotes, invoices, recurring templates, generated invoices, public pages, PDFs, and transaction validation. The Laravel backend is authoritative; browser calculations exist only to provide an immediate preview of the same rules.

## Storage precision

Use PostgreSQL `numeric`, never `real`, `double precision`, PHP floats, or JavaScript `number`, for financial calculations or persisted financial values.

| Value | PostgreSQL type | Meaning |
| --- | --- | --- |
| Unit prices and stored monetary amounts | `numeric(30,8)` | Up to eight fractional digits; computed amounts are additionally quantized to the document precision |
| Quantity and period quantity | `numeric(20,6)` | Up to six fractional digits |
| Discount and tax rates | `numeric(12,6)` | Percentage points; `19` means 19%, not `0.19` |
| Currency precision | `smallint` | Integer from 0 through 8 |

The fixed database scale is a safe storage envelope, not the display or business precision of every currency. The application validates values before persistence and must not rely on PostgreSQL coercion to perform business rounding.

Computed line, document, payment, refund, and adjustment amounts must be exactly representable at their owning document's stored currency precision. Database constraints should verify this where practical, for example by requiring a stored amount to equal `round(amount, currency_precision)`.

## Currency-precision snapshots

Each company configures a precision from 0 through 8 for each enabled currency. ISO minor-unit conventions may supply an initial default, but the company setting is authoritative and may differ from that convention.

Every quote, invoice, and recurring template stores a snapshot of its currency precision when its currency is resolved. Later edits to company currency settings do not silently recalculate or reformat an existing document or template.

- Quote-to-invoice conversion preserves the quote's stored currency and precision.
- A generated recurring invoice copies the recurring template's stored currency and precision.
- Product/service prices may use all eight storage decimals. Once copied, the document line is governed by the document's stored precision.
- Payments, refunds, and adjustments must use the linked invoice's currency and precision.

## Authoritative decimal implementation

Laravel calculations use `brick/math` and `BigDecimal`. Every required rounding step uses:

```php
$value->toScale($scale, RoundingMode::HALF_UP)
```

`RoundingMode::HALF_UP` means round to the nearest value and, on an exact tie, away from zero. Do not substitute BCMath operations whose default truncation would make rounding dependent on individual call sites.

React previews use `decimal.js` configured for the equivalent `Decimal.ROUND_HALF_UP` behavior. Financial values cross the Laravel/Inertia/browser boundary as decimal strings, not JSON numbers. The server recalculates all derived amounts and ignores or rejects client-supplied totals.

## Line calculation order

Let `p` be the document's stored currency precision. Apply the following operations in this order:

```text
items_subtotal = round(item_price × quantity, p, HALF_UP)

if period_unit = NONE:
    items_total = items_subtotal
else:
    items_total = round(items_subtotal × period_quantity, p, HALF_UP)

discount_value = round(
    items_total × discount_percentage ÷ 100,
    p,
    HALF_UP
)

grand_subtotal = items_total − discount_value

tax_value = round(
    grand_subtotal × tax_percentage ÷ 100,
    p,
    HALF_UP
)

final_line_total = grand_subtotal + tax_value
```

Rounding at each specified step, rather than only at the final total, is intentional so the amounts printed on a PDF reconcile when a customer checks the calculation by hand.

`grand_subtotal` and `final_line_total` require no further rounding because they add or subtract values already quantized to `p` decimals. Persist the calculated line snapshots used to produce customer-visible output.

## Document totals and summaries

Document totals aggregate the stored rounded line snapshots only:

```text
document_subtotal = sum(line.grand_subtotal)
document_tax_total = sum(line.tax_value)
document_total = sum(line.final_line_total)
```

Tax summaries group and sum stored rounded line tax values. v1 performs no document-level rerounding, residual-cent allocation, overall discount, invoice-wide tax, or document-level fee.

The editor, saved document, public page, email summary, and generated PDF must all render the same persisted values. Regenerating a PDF must never recalculate a historical document using current company settings.

## Validation rules

- Unit price and stored monetary inputs are non-negative and fit `numeric(30,8)`.
- Quantity is greater than zero and fits `numeric(20,6)` on a valid billable line.
- Period quantity is greater than zero and fits `numeric(20,6)` when a period is used; `NONE` uses multiplier 1.
- Discount percentage is from 0 through 100 inclusive.
- Tax percentage is non-negative and fits `numeric(12,6)`.
- Drafts may temporarily be incomplete, but issue/send and recurring-template activation require valid calculable lines.
- Every calculated result must fit the monetary storage envelope; overflow is a validation failure, never truncation or saturation.

## Verification

Maintain shared golden calculation vectors and execute them against the PHP calculation service and the TypeScript preview implementation. Required cases include:

- Currency precision 0, 2, 3, and 8
- Values immediately below, at, and above half-rounding boundaries
- Fractional prices, quantities, period quantities, discounts, and tax rates
- `NONE`, monthly, and yearly period behavior
- Zero and 100% discounts and zero tax
- Multiple lines whose rounded values expose document-level rerounding errors
- Quote-to-invoice and recurring-template-to-invoice precision preservation
- Company precision changes not affecting existing snapshots
- Payment, refund, and adjustment precision validation
- Identical saved, public-page, and PDF totals that reconcile from printed components

## References

- [PostgreSQL numeric types](https://www.postgresql.org/docs/current/datatype-numeric.html)
- [`brick/math` documentation](https://github.com/brick/math)
