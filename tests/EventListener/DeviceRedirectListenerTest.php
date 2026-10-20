<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\DeviceRedirectListener;
use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DeviceRedirectListenerTest extends TestCase
{
    public function testAViewOnAnotherHostIsRedirectedThere(): void
    {
        [$request, $context] = self::request('http://www.example.com/blog/post?a=1');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://m.example.com/blog/post?a=1&device_view=mobile', $response->getTargetUrl());
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(DeviceType::Mobile, $context->resolved?->view);
    }

    public function testHeadIsRedirectedByDefault(): void
    {
        [$request] = self::request('http://www.example.com/', 'HEAD');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testTheMethodsAndCacheControlAreConfigured(): void
    {
        [$request] = self::request('http://www.example.com/', 'POST');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver(), ['POST'], 'no-cache')->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        [$get] = self::request('http://www.example.com/');
        $getEvent = self::event($get);
        new DeviceRedirectListener(self::policy(), self::resolver(), ['POST'])->onKernelRequest($getEvent);
        self::assertNull($getEvent->getResponse());
    }

    public function testANullCacheControlStoresNothing(): void
    {
        [$request] = self::request('http://www.example.com/');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver(), cacheControl: null)->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(301, $response->getStatusCode());
        self::assertNull($response->headers->get('Cache-Control'), 'a RedirectResponse with status 301 and no Cache-Control removes the header');
    }

    public function testAMethodOutsideTheListIsLeftAlone(): void
    {
        [$request, $context] = self::request('http://www.example.com/', 'POST');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertNull($context->resolved);
    }

    public function testTheCurrentHostIsLeftAlone(): void
    {
        [$request] = self::request('https://m.example.com/');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testWithoutRedirectsTheViewIsNotResolved(): void
    {
        [$request, $context] = self::request('http://www.example.com/');
        $event = self::event($request);

        new DeviceRedirectListener(new RedirectPolicy([], new ViewNames(), new SwitchUrlGenerator()), self::resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertNull($context->resolved);
    }

    public function testASkippedRequestIsNeverRedirected(): void
    {
        $request = Request::create('http://www.example.com/_fragment');
        $context = new DeviceViewContext(skipped: true);
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertNull($context->resolved);
    }

    public function testASubRequestIsNeverRedirected(): void
    {
        [$request, $context] = self::request('http://www.example.com/');
        $event = self::event($request, HttpKernelInterface::SUB_REQUEST);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertNull($context->resolved);
    }

    public function testARequestWithoutContextIsLeftAlone(): void
    {
        $event = self::event(Request::create('http://www.example.com/'));

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @return array{Request, DeviceViewContext}
     */
    private static function request(string $uri, string $method = 'GET'): array
    {
        $request = Request::create($uri, $method);
        $context = new DeviceViewContext();
        $request->attributes->set(DeviceViewContext::ATTRIBUTE, $context);

        return [$request, $context];
    }

    private static function event(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(new Kernel(), $request, $type);
    }

    private static function policy(): RedirectPolicy
    {
        return new RedirectPolicy(
            ['mobile' => ['host' => 'https://m.example.com', 'action' => RedirectAction::Redirect, 'status' => 301, 'keep_query' => true]],
            new ViewNames(),
            new SwitchUrlGenerator(),
        );
    }

    /**
     * A resolver that always gives the mobile view.
     */
    private static function resolver(): ViewResolverInterface
    {
        return new class implements ViewResolverInterface {
            #[\Override]
            public function resolve(Request $request, DeviceViewContext $context): ResolvedView
            {
                return new ResolvedView(DeviceType::Mobile, ViewSource::Detector);
            }
        };
    }
}
