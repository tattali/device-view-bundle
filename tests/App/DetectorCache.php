<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * A PSR-16 cache in memory, for the detector.mobile_detect.cache option.
 */
final class DetectorCache extends Psr16Cache
{
    public function __construct(
        public readonly ArrayAdapter $pool = new ArrayAdapter(),
    ) {
        parent::__construct($pool);
    }
}
