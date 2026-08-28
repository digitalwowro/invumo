<?php

namespace App\Modules\Delivery\Contracts;

use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;

interface SendsProviderEmail
{
    public function send(ProviderDelivery $delivery): ProviderDeliveryResult;
}
