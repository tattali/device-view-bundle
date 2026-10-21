<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Event;

use DeviceViewBundle\Event\BeforeRememberEvent;
use DeviceViewBundle\Remember\RememberTrigger;
use DeviceViewBundle\View\DeviceType;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Records each BeforeRememberEvent of the last main request, as the listener received it, then
 * calls $onEvent, which a test sets to change the lifetime or cancel the remember.
 */
final class BeforeRememberListener
{
    /** @var (\Closure(BeforeRememberEvent): void)|null */
    public ?\Closure $onEvent = null;

    /** @var list<array{trigger: RememberTrigger, view: DeviceType, consent: bool|null, lifetime: int}> */
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

    public function onBeforeRemember(BeforeRememberEvent $event): void
    {
        $this->events[] = ['trigger' => $event->getTrigger(), 'view' => $event->getView(), 'consent' => $event->getConsent(), 'lifetime' => $event->getLifetime()];
        if (null !== $this->onEvent) {
            ($this->onEvent)($event);
        }
    }
}
