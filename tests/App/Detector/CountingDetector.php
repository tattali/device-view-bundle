<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Detector;

use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\Detector\MobileDetectDetector;
use DeviceViewBundle\View\DeviceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Mobile Detect detector, counting its calls.
 */
final class CountingDetector implements DeviceDetectorInterface
{
    public private(set) int $calls = 0;

    private readonly MobileDetectDetector $detector;

    public function __construct()
    {
        $this->detector = new MobileDetectDetector();
    }

    #[\Override]
    public function detect(Request $request): ?DeviceType
    {
        ++$this->calls;

        return $this->detector->detect($request);
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return $this->detector->varyHeaders();
    }
}
