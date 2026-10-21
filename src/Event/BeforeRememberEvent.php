<?php

declare(strict_types=1);

namespace DeviceViewBundle\Event;

use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\View\DeviceType;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched on the response of a main request, right before the bundle stores the view, once
 * the remember decision table (design.md section 7) says it is stored: with the switch trigger
 * for a switch or DeviceView::setView() asked to remember, with the detected trigger for a view
 * from the detector or the trusted header. For a switch redirected on the host its view is
 * served on, the switch listener dispatches it on the request instead, and a cancel keeps the
 * switch on its page; the response then uses that event without dispatching it again, unless
 * the view or the consent changed.
 *
 * A listener may change the lifetime, which the storage then uses as is (0 = until the browser
 * session ends; session storage ignores lifetimes), or cancel the remember: nothing is stored,
 * and the response is not made private for it. A forged stored value is still expired. Nothing
 * dispatches the event when the table, DeviceView::setRemember(false) or the remember route
 * default stop the write, on a skipped request, or without a storage.
 */
final class BeforeRememberEvent extends Event
{
    private bool $cancelled = false;

    /**
     * @param DeviceType $view     the view that is stored, after tablet_as_mobile
     * @param bool|null  $consent  the consent the lifetime comes from: DeviceView::setConsent(), else the consent checker; null when unknown or when no consent provider is set
     * @param int        $lifetime the lifetime in seconds, 0 or more (0 = until the browser session ends)
     *
     * @throws \InvalidArgumentException when the lifetime is negative
     */
    public function __construct(
        private readonly RememberTrigger $trigger,
        private readonly DeviceType $view,
        private readonly ?bool $consent,
        private int $lifetime,
    ) {
        self::checkLifetime($lifetime);
    }

    public function getTrigger(): RememberTrigger
    {
        return $this->trigger;
    }

    public function getView(): DeviceType
    {
        return $this->view;
    }

    /**
     * @return bool|null true when granted, false when denied, null when unknown
     */
    public function getConsent(): ?bool
    {
        return $this->consent;
    }

    /**
     * @return int the lifetime in seconds (0 = until the browser session ends)
     */
    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * @param int $lifetime seconds, 0 or more (0 = until the browser session ends)
     *
     * @throws \InvalidArgumentException when the lifetime is negative
     */
    public function setLifetime(int $lifetime): void
    {
        self::checkLifetime($lifetime);
        $this->lifetime = $lifetime;
    }

    /**
     * Cancels the remember. Later listeners are still called, and see it cancelled.
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    private static function checkLifetime(int $lifetime): void
    {
        if ($lifetime < 0) {
            throw new \InvalidArgumentException(\sprintf('The remember lifetime must be 0 or more seconds; got %d.', $lifetime));
        }
    }
}
