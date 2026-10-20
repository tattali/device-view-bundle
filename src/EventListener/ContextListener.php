<?php

declare(strict_types=1);

namespace DeviceViewBundle\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Attaches a new DeviceViewContext to the main request, on kernel.request at priority 33,
 * before the RouterListener (32), so route conditions can read the view.
 *
 * Nothing is resolved here: the view is resolved the first time something asks for it.
 */
final readonly class ContextListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getRequest()->attributes->set(DeviceViewContext::ATTRIBUTE, new DeviceViewContext());
    }
}
