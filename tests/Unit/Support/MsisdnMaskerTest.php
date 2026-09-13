<?php

namespace Tests\Unit\Support;

use App\Support\MsisdnMasker;
use Tests\TestCase;

class MsisdnMaskerTest extends TestCase
{
    public function test_it_masks_the_middle_of_a_normal_msisdn(): void
    {
        $this->assertSame('254700***789', MsisdnMasker::mask('254700123789'));
    }

    public function test_it_leaves_a_short_value_unmasked(): void
    {
        $this->assertSame('12345', MsisdnMasker::mask('12345'));
    }
}
