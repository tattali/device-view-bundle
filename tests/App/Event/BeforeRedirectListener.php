<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Event;

use DeviceViewBundle\Event\BeforeRedirectEvent;
use DeviceViewBundle\Redirect\RedirectReason;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Records each BeforeRedirectEvent of the last main request, as the listener received it, then
 * calls $onEvent, which a test sets to change or cancel the redirect.
 */
final class BeforeRedirectListener
{
    /** @var (\Closure(BeforeRedirectEvent): void)|null */
    public ?\Closure $onEvent = null;

    /** @var list<array{reason: RedirectReason, url: string, status: int}> */
    public private(set) array $events = [];

    /**
     * Forgets the events of the previous main request, so a kernel handling many requests keeps
     * only the last one.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->events = [];
        }
    }

    public function onBeforeRedirect(BeforeRedirectEvent $event): void
    {
        $this->events[] = ['reason' => $event->getReason(), 'url' => $event->getUrl(), 'status' => $event->getStatus()];
        if (null !== $this->onEvent) {
            ($this->onEvent)($event);
        }
    }
}
