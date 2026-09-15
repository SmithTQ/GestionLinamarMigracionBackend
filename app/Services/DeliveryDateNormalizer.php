<?php

namespace App\Services;

use Carbon\Carbon;
use DateTime;
use InvalidArgumentException;

final class DeliveryDateNormalizer
{
    private const FORMATS = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('La fecha de entrega debe ser una cadena.');
        }

        $value = trim($value);
        foreach (self::FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                $errors = DateTime::getLastErrors();
                $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

                if (! $hasErrors && $date instanceof Carbon && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Try the next documented format before rejecting the value.
            }
        }

        throw new InvalidArgumentException('El formato de delivery_date no es válido.');
    }
}
