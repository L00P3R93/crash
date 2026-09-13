<?php

namespace Tests\Unit\Support;

use App\Support\MsisdnNormalizer;
use Tests\TestCase;

class MsisdnNormalizerTest extends TestCase
{
    public function test_it_leaves_an_already_canonical_number_unchanged(): void
    {
        $this->assertSame('254712345678', MsisdnNormalizer::normalize('254712345678'));
    }

    public function test_it_strips_a_leading_plus(): void
    {
        $this->assertSame('254712345678', MsisdnNormalizer::normalize('+254712345678'));
    }

    public function test_it_converts_a_local_leading_zero(): void
    {
        $this->assertSame('254712345678', MsisdnNormalizer::normalize('0712345678'));
    }

    public function test_it_prefixes_a_bare_nine_digit_number(): void
    {
        $this->assertSame('254712345678', MsisdnNormalizer::normalize('712345678'));
        $this->assertSame('254112345678', MsisdnNormalizer::normalize('112345678'));
    }

    public function test_it_collapses_a_country_code_plus_local_zero_typo(): void
    {
        $this->assertSame('254712345678', MsisdnNormalizer::normalize('+2540712345678'));
    }

    public function test_it_strips_spaces_and_dashes(): void
    {
        $this->assertSame('254712345678', MsisdnNormalizer::normalize('+254 712-345-678'));
    }
}
