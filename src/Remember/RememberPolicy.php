<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

/**
 * The remember decision table of design.md section 7: whether a view is remembered, and for how
 * long.
 *
 * Until a consent provider can be configured, the rows without a provider apply: a switch is
 * remembered for consented_lifetime only when the consent is granted.
 *
 * @internal
 */
final readonly class RememberPolicy
{
    /**
     * @param bool $onSwitch          device_view.remember.on_switch
     * @param bool $detectedView      device_view.remember.detected_view
     * @param int  $lifetime          seconds without consent; 0 = until the browser session ends
     * @param int  $consentedLifetime seconds when the consent is granted
     */
    public function __construct(
        private bool $onSwitch = true,
        private bool $detectedView = false,
        private int $lifetime = 0,
        private int $consentedLifetime = 31536000,
    ) {
    }

    /**
     * @param bool|null $consent true when granted, false when denied, null when unknown
     *
     * @return int|null the lifetime of the write in seconds (0 = until the browser session ends), or null for no write
     */
    public function lifetime(RememberTrigger $trigger, ?bool $consent = null): ?int
    {
        return match ($trigger) {
            RememberTrigger::Switch => $this->onSwitch ? (true === $consent ? $this->consentedLifetime : $this->lifetime) : null,
            RememberTrigger::Detected => $this->detectedView ? $this->lifetime : null,
        };
    }
}
