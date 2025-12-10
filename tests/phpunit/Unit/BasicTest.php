<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

#[\AllowDynamicProperties]
final class BasicTest extends TestCase
{
    protected function setUp(): void
    {
        $this->events = new \stdClass();
        $this->events->id = 1;
    }

    public function testBasicTest(): void
    {
        $this->assertEquals(1, 1);
    }
}
