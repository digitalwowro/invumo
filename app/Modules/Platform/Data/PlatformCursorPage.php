<?php

namespace App\Modules\Platform\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class PlatformCursorPage
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public array $items,
        public ?string $previousUrl,
        public ?string $nextUrl,
    ) {}

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  CursorPaginator<TKey, TValue>  $paginator
     * @param  callable(TValue): array<string, mixed>  $map
     */
    public static function from(CursorPaginator $paginator, callable $map): self
    {
        return new self(
            items: array_values(array_map($map, $paginator->items())),
            previousUrl: $paginator->previousPageUrl(),
            nextUrl: $paginator->nextPageUrl(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'previousUrl' => $this->previousUrl,
            'nextUrl' => $this->nextUrl,
        ];
    }
}
