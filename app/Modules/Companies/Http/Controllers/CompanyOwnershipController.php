<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\TransferCompanyOwnership;
use App\Modules\Companies\Exceptions\CompanyOwnershipTransferException;
use App\Modules\Companies\Http\Requests\TransferCompanyOwnershipRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class CompanyOwnershipController extends Controller
{
    public function update(
        TransferCompanyOwnershipRequest $request,
        Company $company,
        TransferCompanyOwnership $transfer,
    ): RedirectResponse {
        $destination = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->whereKey($request->string('destination_membership_id')->toString())
            ->first();

        if ($destination === null) {
            $this->validationError(CompanyOwnershipTransferException::memberUnavailable());
        }

        $retainFormerOwner = $request->boolean('retain_former_owner', true);

        try {
            $transfer->handle($company, $request->user(), $destination, $retainFormerOwner);
        } catch (CompanyOwnershipTransferException $exception) {
            $this->validationError($exception);
        }

        if (! $retainFormerOwner) {
            $request->session()->forget('last_company_id');
            $request->session()->put('company_context.skip_remember_once', true);

            return redirect()->route('companies.index')
                ->with('status', __('companies_ui.members.feedback.ownership_transferred'));
        }

        return back()->with('status', __('companies_ui.members.feedback.ownership_transferred'));
    }

    private function validationError(CompanyOwnershipTransferException $exception): never
    {
        throw ValidationException::withMessages([
            'ownership' => __("companies_ui.members.errors.{$exception->reason()}"),
        ]);
    }
}
