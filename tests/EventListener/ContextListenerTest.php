<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\ContextListener;
use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ContextListenerTest extends TestCase
{
    public function testItAttachesANewContextToTheMainRequest(): void
    {
        $request = Request::create('/');
        $listener = new ContextListener();

        $listener->onKernelRequest(new RequestEvent(new Kernel(), $request, HttpKernelInterface::MAIN_REQUEST));
        $first = $request->attributes->get('_device_view');
        $listener->onKernelRequest(new RequestEvent(new Kernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertInstanceOf(DeviceViewContext::class, $first);
        self::assertFalse($first->skipped);
        self::assertInstanceOf(DeviceViewContext::class, $request->attributes->get('_device_view'));
        self::assertNotSame($first, $request->attributes->get('_device_view'), 'each handling starts from a new context');
    }

    public function testItLeavesSubRequestsAlone(): void
    {
        $request = Request::create('/');

        new ContextListener()->onKernelRequest(new RequestEvent(new Kernel(), $request, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($request->attributes->has('_device_view'));
    }
}
