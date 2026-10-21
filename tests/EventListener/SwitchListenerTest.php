<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\EventListener;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Event\BeforeRedirectEvent;
use DeviceViewBundle\EventListener\SwitchListener;
use DeviceViewBundle\Redirect\RedirectAction;
use DeviceViewBundle\Redirect\RedirectPolicy;
use DeviceViewBundle\Redirect\RedirectReason;
use DeviceViewBundle\Remember\RememberPolicy;
use DeviceViewBundle\Resolver\Step\SwitchStep;
use DeviceViewBundle\Resolver\ViewResolver;
use DeviceViewBundle\Routing\RouteDefaults;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewNames;
use DeviceViewBundle\ViewSwitch\SwitchParameter;
use DeviceViewBundle\ViewSwitch\SwitchTarget;
use DeviceViewBundle\ViewSwitch\SwitchUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
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

    public function testASwitchToAViewOnAnotherHostGoesThere(): void
    {
        [$request, $context] = self::request('http://www.example.com/view?a=1&device_view=tablet');
        $event = self::event($request);

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), status: 303, redirectPolicy: self::redirectPolicy(), resolver: new ViewResolver([new SwitchStep(new SwitchParameter(new ViewNames()))], tabletAsMobile: true))->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://m.example.com/view?a=1&device_view=mobile', $response->getTargetUrl(), 'the host of the view the request resolves to');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(DeviceType::Tablet, $context->plannedView);
    }

    public function testWithoutAResolverTheSwitchStaysOnTheCurrentHost(): void
    {
        [$request] = self::request('http://www.example.com/view?device_view=mobile');
        $event = self::event($request);

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy())->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/view', $response->getTargetUrl());
    }

    public function testWithoutRedirectsTheSwitchResolvesNothing(): void
    {
        [$request, $context] = self::request('http://www.example.com/view?device_view=mobile');
        $event = self::event($request);

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: new RedirectPolicy([], new ViewNames(), new SwitchUrlGenerator()), resolver: new ViewResolver([]))->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/view', $response->getTargetUrl());
        self::assertNull($context->resolved);
    }

    public function testAnUnrememberedSwitchArrivingOnTheHostOfItsViewIsNotRedirected(): void
    {
        $resolver = new ViewResolver([new SwitchStep(new SwitchParameter(new ViewNames()))]);
        [$request, $context] = self::request('https://m.example.com/view?device_view=mobile');
        $event = self::event($request);

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver, rememberPolicy: new RememberPolicy(onSwitch: false))->onKernelRequest($event);

        self::assertNull($event->getResponse(), 'a redirect to /view would forget the switch');
        self::assertTrue($context->switched);

        [$remembered] = self::request('https://m.example.com/view?device_view=mobile');
        $event = self::event($remembered);
        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver, rememberPolicy: new RememberPolicy())->onKernelRequest($event);
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/view', $response->getTargetUrl(), 'a remembered switch cleans the URL');

        [$withoutPolicy] = self::request('https://m.example.com/view?device_view=mobile');
        $event = self::event($withoutPolicy);
        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver)->onKernelRequest($event);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse(), 'without a remember policy, the switch is taken as remembered');

        [$noHost] = self::request('https://m.example.com/view?device_view=desktop');
        $event = self::event($noHost);
        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver, rememberPolicy: new RememberPolicy(onSwitch: false))->onKernelRequest($event);
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/view', $response->getTargetUrl(), 'a view no redirect serves keeps the switch redirect');
    }

    public function testTheRouteActionDecidesWhereTheSwitchGoes(): void
    {
        $resolver = new ViewResolver([new SwitchStep(new SwitchParameter(new ViewNames()))]);
        $listener = new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver, routeDefaults: new RouteDefaults());

        [$none, $context] = self::request('http://www.example.com/no-redirect?device_view=mobile');
        $none->attributes->set('_device_view_redirect', 'none');
        $event = self::event($none);
        $listener->onKernelRequest($event);
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/no-redirect', $response->getTargetUrl());
        self::assertSame(RedirectAction::None, $context->routeRedirect);

        [$withoutPath] = self::request('http://www.example.com/without-path?a=1&device_view=mobile');
        $withoutPath->attributes->set('_device_view_redirect', 'redirect_without_path');
        $event = self::event($withoutPath);
        $listener->onKernelRequest($event);
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://m.example.com/?device_view=mobile', $response->getTargetUrl());
    }

    public function testASwitchTheRouteDoesNotRememberStaysOnTheHostOfItsView(): void
    {
        $resolver = new ViewResolver([new SwitchStep(new SwitchParameter(new ViewNames()))]);
        $listener = new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver, rememberPolicy: new RememberPolicy(), routeDefaults: new RouteDefaults());

        [$request, $context] = self::request('https://m.example.com/no-remember?device_view=mobile');
        $request->attributes->set('_device_view_remember', false);
        $event = self::event($request);
        $listener->onKernelRequest($event);
        self::assertNull($event->getResponse(), 'a redirect to /no-remember would forget the switch');
        self::assertTrue($context->switched);
        self::assertFalse($context->routeRemember);

        [$remembered] = self::request('https://m.example.com/remember?device_view=mobile');
        $remembered->attributes->set('_device_view_remember', true);
        $event = self::event($remembered);
        $listener->onKernelRequest($event);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse(), 'true lets the policy remember the switch');

        [$withoutReader] = self::request('https://m.example.com/no-remember?device_view=mobile');
        $withoutReader->attributes->set('_device_view_remember', false);
        $event = self::event($withoutReader);
        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), redirectPolicy: self::redirectPolicy(), resolver: $resolver, rememberPolicy: new RememberPolicy())->onKernelRequest($event);
        self::assertInstanceOf(RedirectResponse::class, $event->getResponse(), 'without the reader, routes override nothing');
    }

    public function testBeforeRedirectEventChangesTheRedirect(): void
    {
        [$request] = self::request('/view?a=1&device_view=mobile');
        $event = self::event($request);
        $dispatcher = new EventDispatcher();
        /** @var list<array{RedirectReason, string, int}> $seen */
        $seen = [];
        $dispatcher->addListener(BeforeRedirectEvent::class, static function (BeforeRedirectEvent $redirect) use (&$seen): void {
            $seen[] = [$redirect->getReason(), $redirect->getUrl(), $redirect->getStatus()];
            $redirect->setUrl('/elsewhere');
            $redirect->setStatus(301);
        });

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), status: 303, dispatcher: $dispatcher)->onKernelRequest($event);

        self::assertSame([[RedirectReason::Switch, '/view?a=1', 303]], $seen);
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/elsewhere', $response->getTargetUrl());
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testACancelledRedirectKeepsTheSwitch(): void
    {
        [$request, $context] = self::request('/view?device_view=tablet');
        $event = self::event($request);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BeforeRedirectEvent::class, static function (BeforeRedirectEvent $redirect): void {
            $redirect->cancel();
        });

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), dispatcher: $dispatcher)->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertTrue($context->switched);
        self::assertSame(DeviceType::Tablet, $context->plannedView);
    }

    public function testNoEventWithoutARedirect(): void
    {
        [$request] = self::request('/view?device_view=tablet');
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BeforeRedirectEvent::class, static function (): void {
            self::fail('No redirect, no event.');
        });

        new SwitchListener(new SwitchParameter(new ViewNames()), new SwitchUrlGenerator(), SwitchTarget::None, dispatcher: $dispatcher)->onKernelRequest(self::event($request));

        self::assertTrue($dispatcher->hasListeners(BeforeRedirectEvent::class));
    }

    private static function redirectPolicy(): RedirectPolicy
    {
        return new RedirectPolicy(
            ['mobile' => ['host' => 'https://m.example.com', 'action' => RedirectAction::Redirect, 'status' => 301, 'keep_query' => true]],
            new ViewNames(),
            new SwitchUrlGenerator(),
        );
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
