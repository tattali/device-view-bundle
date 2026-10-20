<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Consent;

use DeviceViewBundle\Consent\ConsentCheckerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A consent checker reading the X-Consent header: "yes" is granted, "no" is denied, anything
 * else is unknown.
 */
final readonly class HeaderConsent implements ConsentCheckerInterface
{
    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        return match ($request->headers->get('X-Consent')) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }
}
