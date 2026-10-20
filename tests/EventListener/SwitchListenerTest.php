<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\SwitchListener;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SwitchListenerTest extends TestCase
{
    public function testAValidSwitchRedirectsToTheCurrentPageByDefault(): void
    {
        [$request, $context] = self::request('/view?a=1&device_view=mobile');
        $event = self::event($request);

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator())->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/view?a=1', $response->getTargetUrl());
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertTrue($context->switched);
        self::assertSame(DeviceType::Mobile, $context->switchView);
        self::assertSame(DeviceType::Mobile, $context->plannedView);
    }

    public function testTheTargetStatusAndCacheControlAreConfigured(): void
    {
        [$request] = self::request('/view?a=1&device_view=mobile');
        $event = self::event($request);

        self::listener(SwitchTarget::Home, 307, 'no-cache')->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/', $response->getTargetUrl());
        self::assertSame(307, $response->getStatusCode());
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
    }

    public function testANullCacheControlStoresNothing(): void
    {
        [$request] = self::request('/view?device_view=mobile');
        $event = self::event($request);

        self::listener(cacheControl: null)->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(302, $response->getStatusCode());
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'), 'Symfony\'s computed value: nothing is stored');
    }

    public function testTargetNoneSwitchesWithoutRedirect(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $event = self::event($request);

        self::listener(SwitchTarget::None)->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertTrue($context->switched);
        self::assertSame(DeviceType::Tablet, $context->switchView);
    }

    public function testWithoutAValidSwitchNothingHappens(): void
    {
        [$request, $context] = self::request('/view?device_view=phone');
        $event = self::event($request);

        self::listener()->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertFalse($context->switched);
    }

    public function testASubRequestNeverSwitches(): void
    {
        // a duplicated request (a forward, a fragment) carries the attributes of its main request
        [$request, $context] = self::request('/view?device_view=mobile');
        $event = self::event($request, HttpKernelInterface::SUB_REQUEST);

        self::listener()->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertFalse($context->switched);
        self::assertNull($context->switchView);
    }

    public function testARequestWithoutContextIsLeftAlone(): void
    {
        $request = Request::create('/view?device_view=mobile');
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, 'not a context');
        $event = self::event($request);

        self::listener()->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @return array{Request, DeviceViewContext}
     */
    private static function request(string $uri): array
    {
        $request = Request::create($uri);
        $context = new DeviceViewContext();
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);

        return [$request, $context];
    }

    private static function event(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(new Kernel(), $request, $type);
    }

    private static function listener(SwitchTarget $target = SwitchTarget::Current, int $status = 302, ?string $cacheControl = 'no-store, private'): SwitchListener
    {
        return new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), $target, $status, $cacheControl);
    }
}
