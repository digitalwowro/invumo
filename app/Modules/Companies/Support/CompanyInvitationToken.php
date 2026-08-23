<?php

namespace App\Modules\Companies\Support;

use Illuminate\Support\Str;

final readonly class CompanyInvitationToken
{
    private function __construct(private string $plainText) {}

    public static function issue(): self
    {
        return new self(Str::random(64));
    }

    public static function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    public function plainText(): string
    {
        return $this->plainText;
    }

    public function hashed(): string
    {
        return self::hash($this->plainText);
    }
}
