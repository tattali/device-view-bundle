<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\ContextListener;
use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'the fragment path' => ['/_fragment', true];
        yield 'the fragment path, encoded' => ['/_fr%61gment', true];
        yield 'below the fragment path' => ['/_fragment/x', false];
        yield 'above the fragment path' => ['/x/_fragment', false];
        yield 'a skip path' => ['/api/thing', true];
        yield 'a skip path, encoded' => ['/%61pi/thing', true];
        yield 'the second skip path' => ['/_wdt/abc', true];
        yield 'a skip path not at the start' => ['/x/api', false];
        yield 'another path' => ['/view', false];
    }

    #[DataProvider('paths')]
    public function testItMarksTheContextOfASkippedPath(string $path, bool $skipped): void
    {
        $request = Request::create('http://localhost'.$path);

        new ContextListener('/_fragment', ['^/api', '^/_wdt'])->onKernelRequest(new RequestEvent(new Kernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        $context = $request->attributes->get('_device_view');
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertSame($skipped, $context->skipped);
    }

    public function testItMatchesThePathInfo(): void
    {
        $request = Request::create('http://localhost/app/api', server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/srv/app/index.php']);

        new ContextListener(null, ['^/api$'])->onKernelRequest(new RequestEvent(new Kernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        $context = $request->attributes->get('_device_view');
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertTrue($context->skipped);
    }

    public function testWithoutAFragmentPathTheFragmentPathIsNotSkipped(): void
    {
        $request = Request::create('http://localhost/_fragment');

        new ContextListener(null, [])->onKernelRequest(new RequestEvent(new Kernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        $context = $request->attributes->get('_device_view');
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertFalse($context->skipped);
    }
}
