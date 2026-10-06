<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Formatting\DigitFormatter;
use PHPUnit\Framework\TestCase;

class DigitFormatterTest extends TestCase
{
    public function test_persian_numbering_system_shapes_latin_digits(): void
    {
        $formatter = new DigitFormatter();

        $this->assertSame('۱۴۰۵/۰۷/۰۹', $formatter->format('1405/07/09', 'persian'));
    }

    public function test_latin_numbering_system_preserves_digits(): void
    {
        $formatter = new DigitFormatter();

        $this->assertSame('2026-10-01', $formatter->format('2026-10-01', 'latin'));
    }
}
