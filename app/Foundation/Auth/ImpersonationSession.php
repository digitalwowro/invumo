<?php

namespace App\Foundation\Auth;

use Illuminate\Http\Request;
use LogicException;

final readonly class ImpersonationSession
{
    private const ORIGINAL_USER_ID = 'platform_impersonation.original_user_id';

    private const STARTED_AT = 'platform_impersonation.started_at';

    private const ORIGINAL_COMPANY_ID = 'platform_impersonation.original_company_id';

    public function active(Request $request): bool
    {
        return $this->originalUserId($request) !== null;
    }

    public function begin(Request $request, string $originalUserId, ?string $originalCompanyId): void
    {
        if ($this->active($request)) {
            throw new LogicException('Nested impersonation is prohibited.');
        }

        $request->session()->put([
            self::ORIGINAL_USER_ID => $originalUserId,
            self::STARTED_AT => now()->toIso8601String(),
            self::ORIGINAL_COMPANY_ID => $originalCompanyId,
        ]);
    }

    public function originalUserId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(self::ORIGINAL_USER_ID);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function originalCompanyId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(self::ORIGINAL_COMPANY_ID);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget([
            self::ORIGINAL_USER_ID,
            self::STARTED_AT,
            self::ORIGINAL_COMPANY_ID,
        ]);
    }
}
