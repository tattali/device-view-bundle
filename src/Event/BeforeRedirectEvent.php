<?php

declare(strict_types=1);

namespace DeviceViewBundle\Event;

use DeviceViewBundle\Redirect\RedirectReason;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the bundle answers a main request with a redirect: by the switch listener
 * (reason switch) and by the device redirect listener (reason device).
 *
 * A listener may change the URL of the Location or the status, or cancel the redirect. A
 * cancelled switch redirect keeps the switch: the request uses the switched view and remembers
 * it, as with switch.target none, so the device redirect may still send a view served on another
 * host there, with its own event. A cancelled device redirect leaves the request on the current
 * host. The redirect keeps redirect.cache_control whatever the listeners change.
 */
final class BeforeRedirectEvent extends Event
{
    /**
     * The statuses a redirect of the bundle may have.
     *
     * @var list<int>
     */
    private const array STATUSES = [301, 302, 303, 307, 308];

    private bool $cancelled = false;

    /**
     * @param string $url    the Location: relative for a switch on the current host, absolute for another host
     * @param int    $status the redirect status: 301, 302, 303, 307 or 308
     *
     * @throws \InvalidArgumentException when the status is not a redirect status of the bundle
     */
    public function __construct(
        private readonly RedirectReason $reason,
        private string $url,
        private int $status,
    ) {
        self::checkStatus($status);
    }

    public function getReason(): RedirectReason
    {
        return $this->reason;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status 301, 302, 303, 307 or 308
     *
     * @throws \InvalidArgumentException when the status is not one of them
     */
    public function setStatus(int $status): void
    {
        self::checkStatus($status);
        $this->status = $status;
    }

    /**
     * Cancels the redirect. Later listeners are still called, and see it cancelled.
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    private static function checkStatus(int $status): void
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(\sprintf('The redirect status must be one of %s; got %d.', implode(', ', self::STATUSES), $status));
        }
    }
}
