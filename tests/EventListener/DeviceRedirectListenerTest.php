<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\EventListener\DeviceRedirectListener;
use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Resolver\ResolvedView;
use DeviceViewBundle\Resolver\ViewResolverInterface;
use DeviceViewBundle\Routing\RouteDefaults;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\View\ViewSource;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
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

    public function testARequestCarryingAViewServedHereStays(): void
    {
        // the resolver gives mobile: only the carried desktop view keeps the request on www
        $policy = new RedirectPolicy(
            [
                'mobile' => ['host' => 'https://m.example.com', 'action' => RedirectAction::Redirect, 'status' => 302, 'keep_query' => true],
                'desktop' => ['host' => 'https://www.example.com', 'action' => RedirectAction::Redirect, 'status' => 302, 'keep_query' => true],
            ],
            new ViewNames(),
            new SwitchUrlGenerator(),
        );
        $parameter = new SwitchParameter(new ViewNames(), enabled: false);

        [$here, $context] = self::request('https://www.example.com/view?device_view=desktop');
        $event = self::event($here);
        new DeviceRedirectListener($policy, self::resolver(), switchParameter: $parameter)->onKernelRequest($event);
        self::assertNull($event->getResponse());
        self::assertNull($context->resolved, 'the view stays lazy');

        [$elsewhere] = self::request('https://www.example.com/view?device_view=mobile');
        $event = self::event($elsewhere);
        new DeviceRedirectListener($policy, self::resolver(), switchParameter: $parameter)->onKernelRequest($event);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse(), 'a carried view served on another host does not keep the request here');

        [$withoutParameter] = self::request('https://www.example.com/view?device_view=desktop');
        $event = self::event($withoutParameter);
        new DeviceRedirectListener($policy, self::resolver())->onKernelRequest($event);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse(), 'without the switch parameter, nothing is carried');
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

    public function testANoneRouteIsNeverRedirectedAndLeavesTheViewLazy(): void
    {
        [$request, $context] = self::request('http://www.example.com/no-redirect');
        $request->attributes->set('_device_view_redirect', 'none');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver(), routeDefaults: new RouteDefaults())->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertNull($context->resolved, 'the view stays lazy');
        self::assertSame(RedirectAction::None, $context->routeRedirect);
    }

    public function testWithoutTheReaderRoutesOverrideNothing(): void
    {
        [$request] = self::request('http://www.example.com/no-redirect');
        $request->attributes->set('_device_view_redirect', 'none');
        $event = self::event($request);

        new DeviceRedirectListener(self::policy(), self::resolver())->onKernelRequest($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    public function testTheRouteActionReplacesTheConfiguredOne(): void
    {
        $policy = new RedirectPolicy(
            ['mobile' => ['host' => 'https://m.example.com', 'action' => RedirectAction::None, 'status' => 302, 'keep_query' => true]],
            new ViewNames(),
            new SwitchUrlGenerator(),
        );
        [$request] = self::request('http://www.example.com/blog/post?a=1');
        $request->attributes->set('_device_view_redirect', 'redirect_without_path');
        $event = self::event($request);

        new DeviceRedirectListener($policy, self::resolver(), routeDefaults: new RouteDefaults())->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://m.example.com/?device_view=mobile', $response->getTargetUrl());
    }

    public function testTheRouteActionDecidesWhetherACarriedViewIsServedHere(): void
    {
        // the desktop redirect has action none: only the route makes www the desktop host
        $policy = new RedirectPolicy(
            [
                'mobile' => ['host' => 'https://m.example.com', 'action' => RedirectAction::Redirect, 'status' => 302, 'keep_query' => true],
                'desktop' => ['host' => 'https://www.example.com', 'action' => RedirectAction::None, 'status' => 302, 'keep_query' => true],
            ],
            new ViewNames(),
            new SwitchUrlGenerator(),
        );
        $parameter = new SwitchParameter(new ViewNames(), enabled: false);

        [$request, $context] = self::request('https://www.example.com/always-redirect?device_view=desktop');
        $request->attributes->set('_device_view_redirect', 'redirect');
        $event = self::event($request);
        new DeviceRedirectListener($policy, self::resolver(), switchParameter: $parameter, routeDefaults: new RouteDefaults())->onKernelRequest($event);
        self::assertNull($event->getResponse());
        self::assertNull($context->resolved);

        [$withoutRoute] = self::request('https://www.example.com/view?device_view=desktop');
        $event = self::event($withoutRoute);
        new DeviceRedirectListener($policy, self::resolver(), switchParameter: $parameter, routeDefaults: new RouteDefaults())->onKernelRequest($event);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
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
