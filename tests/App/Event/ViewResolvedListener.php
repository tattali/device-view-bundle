<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App\Event;

use DeviceViewBundle\Event\ViewResolvedEvent;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Records the ViewResolvedEvent of the last main request, as the listener received it, then calls
 * $onEvent, which a test sets to change the view.
 */
final class ViewResolvedListener
{
    /** @var (\Closure(ViewResolvedEvent): void)|null */
    public ?\Closure $onEvent = null;

    /** @var list<array{view: DeviceType, source: ViewSource, request: Request}> */
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

    public function onViewResolved(ViewResolvedEvent $event): void
    {
        $this->events[] = ['view' => $event->getView(), 'source' => $event->getSource(), 'request' => $event->getRequest()];
        if (null !== $this->onEvent) {
            ($this->onEvent)($event);
        }
    }
}
