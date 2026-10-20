<?php

declare(strict_types=1);

namespace DeviceViewBundle\Detector;

use DeviceViewBundle\View\DeviceType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tells the device type of a request.
 *
 * Implementations are shared between requests, so they keep no request state: everything they
 * need comes from the Request they are given, never from $_SERVER.
 */
interface DeviceDetectorInterface
{
    /**
     * Returns the device type of the request, or null when the detector cannot tell.
     */
    public function detect(Request $request): ?DeviceType;

    /**
     * Returns the request headers detect() reads, which the response lists in Vary.
     *
     * @return list<string>
     */
    public function varyHeaders(): array;
}
