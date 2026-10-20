<?php

declare(strict_types=1);

namespace DeviceViewBundle\Remember;

/**
 * The remember decision table of design.md section 7: whether a view is remembered, and for how
 * long.
 *
 * Granted consent, from the consent checker or DeviceView::setConsent(), gives consented_lifetime;
 * otherwise a switch gets lifetime. A detected view gets lifetime when no consent provider is
 * configured, and needs granted consent when one is.
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
     * @param bool $consentProvider   whether device_view.consent.provider is set
     */
    public function __construct(
        private bool $onSwitch = true,
        private bool $detectedView = false,
        private int $lifetime = 0,
        private int $consentedLifetime = 31536000,
        private bool $consentProvider = false,
    ) {
    }

    /**
     * Whether the trigger may write at all, whatever the consent: when it may not, the consent
     * is not asked.
     */
    public function remembers(RememberTrigger $trigger): bool
    {
        return match ($trigger) {
            RememberTrigger::Switch => $this->onSwitch,
            RememberTrigger::Detected => $this->detectedView,
        };
    }

    /**
     * @param bool|null $consent true when granted, false when denied, null when unknown
     *
     * @return int|null the lifetime of the write in seconds (0 = until the browser session ends), or null for no write
     */
    public function lifetime(RememberTrigger $trigger, ?bool $consent = null): ?int
    {
        if (!$this->remembers($trigger)) {
            return null;
        }

        if (true === $consent) {
            return $this->consentedLifetime;
        }

        return RememberTrigger::Detected === $trigger && $this->consentProvider ? null : $this->lifetime;
    }
}
