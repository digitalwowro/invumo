<?php

namespace App\Modules\Companies\Http\Requests;

use App\Modules\Companies\Data\CompanyAppearanceData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateCompanyAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'primary_brand_color' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'logo' => ['nullable', 'file', 'max:5120'],
            'remove_logo' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $fields = __('companies_ui.settings.appearance.fields');

        return is_array($fields) ? $fields : [];
    }

    public function appearance(): CompanyAppearanceData
    {
        return new CompanyAppearanceData(
            primaryBrandColor: (string) $this->validated('primary_brand_color'),
            logo: $this->file('logo'),
            removeLogo: $this->boolean('remove_logo'),
        );
    }

    protected function prepareForValidation(): void
    {
        $color = $this->input('primary_brand_color');

        if (is_string($color)) {
            $this->merge(['primary_brand_color' => strtoupper(trim($color))]);
        }

        if (! $this->exists('remove_logo')) {
            $this->merge(['remove_logo' => false]);
        }
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->hasFile('logo') && $this->boolean('remove_logo')) {
                $validator->errors()->add(
                    'logo',
                    __('companies_ui.settings.appearance.errors.logo_and_removal'),
                );
            }
        }];
    }
}
