<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Data\SendPaymentReceivedData;
use Illuminate\Foundation\Http\FormRequest;

final class SendPaymentReceivedRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'delivery_key' => ['required', 'uuid'],
            'transaction_edit_version' => ['required', 'integer', 'min:1'],
            'confirmed' => ['accepted'],
        ];
    }

    public function delivery(): SendPaymentReceivedData
    {
        return new SendPaymentReceivedData(
            deliveryKey: (string) $this->validated('delivery_key'),
            transactionEditVersion: (int) $this->validated('transaction_edit_version'),
            confirmed: $this->boolean('confirmed'),
        );
    }
}
