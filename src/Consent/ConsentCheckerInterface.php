<?php

declare(strict_types=1);

namespace DeviceViewBundle\Consent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells whether the visitor consented to the view being remembered for
 * device_view.remember.consented_lifetime (design.md sections 7 and 8).
 *
 * Set device_view.consent.provider to the name of a built-in reader of a consent tool, or to the
 * id of your own service implementing this interface. The bundle asks it only when a remember
 * write is planned. Implementations are shared between requests, so they keep no request state.
 */
interface ConsentCheckerInterface
{
    /**
     * Never throws: input that cannot be read gives null.
     *
     * @return bool|null true when consent is granted, false when it is denied, null when no choice was made yet or the input cannot be read; null counts as not granted
     */
    public function hasConsent(Request $request): ?bool;
}
