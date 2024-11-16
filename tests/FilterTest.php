<?php

declare(strict_types=1);

namespace PetrKnap\ExternalFilter;

use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    public function testFactoryWorks()
    {
        self::assertInstanceOf(
            Filter::class,
            Filter::new(command: 'php'),
        );
    }
}
