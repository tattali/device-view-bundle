<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Routing\NoRouteCollectionRouter;
use DeviceViewBundle\Tests\App\TestLogger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The per-route overrides: the route defaults _device_view_redirect and _device_view_remember
 * (route_defaults.redirect and route_defaults.remember), read from the matched route.
 */
final class RouteDefaultsTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array M = ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']];
    private const array D = ['desktop' => ['enabled' => true, 'host' => 'https://www.example.com']];

    #[Group('RTE-01')]
    public function testRedirectNone(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/no-redirect', 'iphone')));
    }

    #[Group('RTE-02')]
    public function testRedirectWithoutPath(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/without-path?a=1', 'iphone')));
    }

    #[Group('RTE-03')]
    public function testRedirectOverActionNone(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'action' => 'none']]]);

        self::assertRedirect('https://m.example.com/always-redirect?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/always-redirect', 'iphone')));
    }

    public function testARouteDefaultDoesNotEnableARedirect(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('desktop detector', $this->handle(self::request('http://www.example.com/always-redirect')));
    }

    public function testTheRouteRedirectKeepsTheOtherOptionsOfTheView(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com/shop', 'action' => 'none', 'status' => 307, 'keep_query' => false]]]);

        self::assertRedirect('https://m.example.com/shop/always-redirect?device_view=mobile', 307, $this->handle(self::request('http://www.example.com/always-redirect?a=1', 'iphone')));
    }

    #[Group('RTE-04')]
    public function testAnInvalidRedirectDefaultIsIgnored(): void
    {
        $this->boot(['redirect' => self::M], ['logger' => TestLogger::class]);

        self::assertRedirect('https://m.example.com/bad-default?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/bad-default', 'iphone')));
        self::assertSame(['Ignored the invalid route default _device_view_redirect "sometimes".'], $this->routeDefaultLogs());
    }

    #[Group('RTE-05')]
    public function testRedirectNoneKeepsTheSwitch(): void
    {
        $this->boot(['redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/no-redirect?device_view=desktop', 'iphone'));

        self::assertRedirect('/no-redirect', 302, $response);
        self::assertSetCookie(self::sc('desktop'), $response);
    }

    public function testRedirectNoneKeepsASwitchToAnotherHostOnTheCurrentHost(): void
    {
        $this->boot(['redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/no-redirect?device_view=mobile'));

        self::assertRedirect('/no-redirect', 302, $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    public function testRedirectWithoutPathSendsASwitchToTheHomePageOfTheOtherHost(): void
    {
        $this->boot(['redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/without-path?a=1&device_view=mobile'));

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    public function testRedirectSendsASwitchToTheOtherHostOverActionNone(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'action' => 'none']]]);

        self::assertRedirect('https://m.example.com/always-redirect?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/always-redirect?device_view=mobile')));
    }

    #[Group('RTE-06')]
    public function testRememberFalseStopsASwitchWrite(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/no-remember?device_view=mobile'));

        self::assertRedirect('/no-remember', 302, $response);
        self::assertNoCookie($response);
    }

    #[Group('RTE-07')]
    public function testRememberFalseStopsADetectedWrite(): void
    {
        $this->boot(['remember' => ['detected_view' => true]]);

        self::assertPage('mobile detector', $this->handle(self::request('/no-remember', 'iphone')));
    }

    public function testRememberFalseStillExpiresAForgedCookie(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/no-remember', cookies: ['device_view' => 'phone']));

        self::assertSame('desktop detector', $response->getContent());
        self::assertSetCookie(self::scDel(), $response);
    }

    #[Group('RTE-08')]
    public function testRememberFalseAsAString(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/no-remember-xml?device_view=mobile'));

        self::assertSame(302, $response->getStatusCode());
        self::assertNoCookie($response);
    }

    #[Group('RTE-09')]
    public function testRememberTrueDoesNotBypassConsent(): void
    {
        $this->boot(['consent' => ['provider' => 'tarteaucitron'], 'remember' => ['detected_view' => true]]);

        self::assertPage('mobile detector', $this->handle(self::request('/remember', 'iphone', cookies: ['tarteaucitron' => '!device_view=false'])));
    }

    public function testRememberTrueLetsThePolicyDecide(): void
    {
        $this->boot();

        $response = $this->handle(self::request('/remember?device_view=mobile'));
        self::assertRedirect('/remember', 302, $response);
        self::assertSetCookie(self::sc('mobile'), $response);

        $this->boot(['remember' => ['on_switch' => false]]);
        self::assertNoCookie($this->handle(self::request('/remember?device_view=mobile')));
    }

    public function testASwitchNotRememberedOnTheRouteStaysOnTheHostOfItsView(): void
    {
        // as REG-21, with the route default instead of remember.on_switch: the redirect to the
        // page without the parameter would forget the view, and the desktop redirect would follow
        $this->boot(['redirect' => self::M + self::D]);

        $response = $this->handle(self::request('https://m.example.com/no-remember?device_view=mobile'));

        self::assertPage('mobile switch', $response);
    }

    public function testASwitchNotRememberedOnTheRouteStillLeavesAnotherHost(): void
    {
        $this->boot(['redirect' => self::M + self::D]);

        $response = $this->handle(self::request('https://m.example.com/no-remember?device_view=desktop', 'iphone'));

        self::assertRedirect('https://www.example.com/no-remember?device_view=desktop', 302, $response);
        self::assertNoCookie($response);
    }

    #[Group('RTE-10')]
    public function testCustomRouteDefaultKeys(): void
    {
        $this->boot(['redirect' => self::M, 'route_defaults' => ['redirect' => '_dv_redirect', 'remember' => '_dv_remember']]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/custom-keys', 'iphone')));

        $response = $this->handle(self::request('http://www.example.com/custom-keys?device_view=desktop', 'iphone'));
        self::assertRedirect('/custom-keys', 302, $response);
        self::assertNoCookie($response);

        self::assertRedirect('https://m.example.com/no-redirect?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/no-redirect', 'iphone')));
    }

    public function testTheDefaultRememberKeyIsNoLongerReadWithCustomKeys(): void
    {
        $this->boot(['route_defaults' => ['remember' => '_dv_remember']]);

        $response = $this->handle(self::request('/no-remember?device_view=mobile'));

        self::assertSetCookie(self::sc('mobile'), $response);
    }

    #[Group('REG-15')]
    public function testARouteOverrideWithoutLoadingTheRouteCollection(): void
    {
        $this->boot(['redirect' => self::M], decorators: ['test.router' => [NoRouteCollectionRouter::class, 'router']]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/no-redirect', 'iphone'), false));
        self::assertNoCookie($this->handle(self::request('http://www.example.com/no-remember?device_view=desktop', 'iphone'), false));
    }

    public function testTheDecoratedRouterRefusesToLoadTheRouteCollection(): void
    {
        $this->boot(decorators: ['test.router' => [NoRouteCollectionRouter::class, 'router']]);
        $router = $this->service('test.router');
        self::assertInstanceOf(NoRouteCollectionRouter::class, $router);

        $this->expectException(\LogicException::class);
        $router->getRouteCollection();
    }

    private static function assertRedirect(string $location, int $status, Response $response): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($location, $response->headers->get('Location'));
    }

    private static function assertPage(string $body, Response $response): void
    {
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($body, $response->getContent());
        self::assertFalse($response->headers->has('Location'));
        self::assertSame([], $response->headers->getCookies());
    }

    /**
     * @return list<string> the debug logs about route defaults
     */
    private function routeDefaultLogs(): array
    {
        $logger = $this->service('logger');
        self::assertInstanceOf(TestLogger::class, $logger);

        return array_values(array_filter($logger->messages(LogLevel::DEBUG), static fn (string $message): bool => str_contains($message, 'route default')));
    }
}
