<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\App;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Records the responses of the sub-requests of the last main request, on kernel.response at
 * priority -1000, after every listener of the bundle.
 */
final class ResponseRecorder
{
    /** @var list<Response> */
    public private(set) array $subResponses = [];

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->subResponses = [];
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            $this->subResponses[] = $event->getResponse();
        }
    }
}
