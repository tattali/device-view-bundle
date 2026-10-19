<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Fixtures\ScenarioCitation;

use PHPUnit\Framework\Attributes\Group;

/**
 * Cites a scenario whose number has three digits.
 */
final class ThreeDigitCitingClass
{
    #[Group('CFG-100')]
    public function testOne(): void
    {
    }
}
