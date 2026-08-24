<?php

namespace App\Modules\Platform\Queries;

use App\Modules\Companies\Models\Company;
use App\Modules\Platform\Data\PlatformCursorPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final readonly class PlatformCompaniesPage
{
    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $search = trim($request->string('q')->toString());
        $page = Company::query()
            ->with(['owningAccount.owner:id,name,email'])
            ->withCount('memberships')
            ->when($search !== '', fn (Builder $query) => $query
                ->whereRaw('lower(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(25)
            ->withQueryString();

        return [
            'search' => $search,
            'page' => PlatformCursorPage::from($page, fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'ownerName' => $company->owningAccount->owner->name,
                'ownerEmail' => $company->owningAccount->owner->email,
                'memberCount' => $company->memberships_count,
                'archived' => $company->archived_at !== null,
                'createdAt' => $company->created_at?->toIso8601String(),
            ])->toArray(),
        ];
    }
}
