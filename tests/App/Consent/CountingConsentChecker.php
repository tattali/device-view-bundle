<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Consent;

use DeviceViewBundle\Consent\ConsentCheckerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Wraps a consent checker and counts the calls to hasConsent().
 */
final class CountingConsentChecker implements ConsentCheckerInterface
{
    public private(set) int $calls = 0;

    public function __construct(
        private readonly ConsentCheckerInterface $inner,
    ) {
    }

    #[\Override]
    public function hasConsent(Request $request): ?bool
    {
        ++$this->calls;

        return $this->inner->hasConsent($request);
    }
}
