<?php

namespace App\Modules\Audit\Data;

use InvalidArgumentException;

final readonly class AuditPayload
{
    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private array $values) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowedFields
     */
    public static function fromAllowedFields(array $values, array $allowedFields): self
    {
        $unexpectedFields = array_diff(array_keys($values), $allowedFields);

        if ($unexpectedFields !== []) {
            throw new InvalidArgumentException(sprintf(
                'Audit payload contains fields outside its action allowlist: %s.',
                implode(', ', $unexpectedFields),
            ));
        }

        return new self($values);
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }
}
