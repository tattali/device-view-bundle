<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Detector;

use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\View\DeviceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * A custom detector: the X-Test-View header names the device type.
 */
final class HeaderDetector implements DeviceDetectorInterface
{
    #[\Override]
    public function detect(Request $request): ?DeviceType
    {
        return DeviceType::tryFrom((string) $request->headers->get('X-Test-View'));
    }

    #[\Override]
    public function varyHeaders(): array
    {
        return ['X-Test-View'];
    }
}
