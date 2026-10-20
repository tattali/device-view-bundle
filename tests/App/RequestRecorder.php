<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Records the requests of the last main request: the main request and its sub-requests.
 */
final class RequestRecorder
{
    /** @var list<Request> */
    public private(set) array $requests = [];

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->requests = [];
        }

        $this->requests[] = $event->getRequest();
    }
}
