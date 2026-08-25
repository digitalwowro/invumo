<?php

namespace App\Modules\Companies\Data;

enum BankRoutingField: string
{
    case RoutingNumber = 'routing_number';
    case SortCode = 'sort_code';
    case BankCode = 'bank_code';
    case BranchCode = 'branch_code';
    case TransitNumber = 'transit_number';
    case InstitutionNumber = 'institution_number';
    case Bsb = 'bsb';
    case Ifsc = 'ifsc';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $field): string => $field->value,
            self::cases(),
        );
    }
}
