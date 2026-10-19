<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Fixtures\ScenarioCitation;

use PHPUnit\Framework\Attributes\Group;

/**
 * Cites a scenario ID with a one-digit number, which the catalogue never defines.
 */
final class MalformedCitingClass
{
    #[Group('SW-1')]
    public function testOne(): void
    {
    }
}
