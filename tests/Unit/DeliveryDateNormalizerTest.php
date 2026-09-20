<?php

namespace Tests\Unit;

use App\Services\DeliveryDateNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeliveryDateNormalizerTest extends TestCase
{
    #[DataProvider('validDates')]
    public function test_normalizes_documented_formats(string $input, string $expected): void
    {
        $this->assertSame($expected, DeliveryDateNormalizer::normalize($input));
    }

    public function test_empty_values_become_null(): void
    {
        $this->assertNull(DeliveryDateNormalizer::normalize(null));
        $this->assertNull(DeliveryDateNormalizer::normalize(''));
        $this->assertNull(DeliveryDateNormalizer::normalize('   '));
    }

    #[DataProvider('invalidDates')]
    public function test_rejects_invalid_or_undocumented_formats(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        DeliveryDateNormalizer::normalize($input);
    }

    public static function validDates(): array
    {
        return [
            ['2026-12-31', '2026-12-31'],
            ['31/12/2026', '2026-12-31'],
            ['31-12-2026', '2026-12-31'],
        ];
    }

    public static function invalidDates(): array
    {
        return [
            ['31/02/2026'],
            ['2026/12/31'],
            ['12.31.2026'],
            ['31/12/26'],
        ];
    }
}
