<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Fixtures\ScenarioCitation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A class shaped like a test case, used to check how scenario citations are read.
 *
 * Its name does not end with "Test", so PHPUnit never runs it.
 */
#[Group('CFG-02')]
#[Group('functional')]
final class CitingClass
{
    #[Group('SW-03')]
    public function testOne(): void
    {
    }

    #[Group('SW-03')]
    #[Group('CON-TAC-04')]
    #[Group('slow')]
    public function testTwo(): void
    {
    }

    #[Test]
    #[Group('SW-03')]
    public function itIsATestThroughItsAttribute(): void
    {
    }

    #[Group('RED-01')]
    public function helperThatIsNotATest(): void
    {
    }
}
