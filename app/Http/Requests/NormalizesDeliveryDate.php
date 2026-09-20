<?php

namespace App\Http\Requests;

use App\Services\DeliveryDateNormalizer;
use InvalidArgumentException;

trait NormalizesDeliveryDate
{
    protected function prepareDeliveryDateForValidation(): void
    {
        if (! $this->has('delivery_date')) {
            return;
        }

        try {
            $this->merge(['delivery_date' => DeliveryDateNormalizer::normalize($this->input('delivery_date'))]);
        } catch (InvalidArgumentException) {
            // Keep the original value so date_format validation returns HTTP 422.
        }
    }
}
