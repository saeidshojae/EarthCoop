<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Formatting\DigitNormalizer;
use PHPUnit\Framework\TestCase;

class DigitNormalizerTest extends TestCase
{
    public function test_persian_digits_are_normalized_to_latin(): void
    {
        $normalizer = new DigitNormalizer();

        $this->assertSame('1405/07/09', $normalizer->toLatin('۱۴۰۵/۰۷/۰۹'));
    }

    public function test_arabic_indic_digits_are_normalized_to_latin(): void
    {
        $normalizer = new DigitNormalizer();

        $this->assertSame('1405/07/09', $normalizer->toLatin('١٤٠٥/٠٧/٠٩'));
    }
}
