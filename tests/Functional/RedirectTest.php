<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Tests\App\Detector\CountingDetector;
use DeviceViewBundle\Tests\App\TestLogger;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The device redirects to the host of each view (redirect.<view>), through the kernel.
 */
final class RedirectTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array M = ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']];
    private const array T = ['tablet' => ['enabled' => true, 'host' => 'https://t.example.com']];
    private const array D = ['desktop' => ['enabled' => true, 'host' => 'https://www.example.com']];

    private const string CROSS_HOST_WARNING = 'The mobile redirect goes to m.example.com, but the device_view cookie has no domain, so that host cannot read the remembered view. Set device_view.remember.cookie.domain to a domain both hosts share.';

    /**
     * Booting with framework.trusted_proxies sets the trusted proxies of every Request, and the
     * env var tests set DV_MOBILE_HOST: both outlive the test.
     */
    #[After]
    public function resetGlobals(): void
    {
        Request::setTrustedProxies([], 0);
        unset($_SERVER['DV_MOBILE_HOST'], $_ENV['DV_MOBILE_HOST']);
    }

    #[Group('RED-01')]
    public function testMobileRedirect(): void
    {
        $this->boot(['redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/blog/post?page=2', 'iphone'));

        self::assertRedirect('https://m.example.com/blog/post?page=2&device_view=mobile', 302, $response);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
        self::assertNoCookie($response);
    }

    #[Group('RED-02')]
    #[Group('REG-18')]
    public function testDesktopRedirect(): void
    {
        $this->boot(['redirect' => self::D]);

        $response = $this->handle(self::request('https://m.example.com/blog/post'));
        self::assertRedirect('https://www.example.com/blog/post?device_view=desktop', 302, $response);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));

        self::assertRedirect('https://www.example.com/?device_view=desktop', 302, $this->handle(self::request('https://m.example.com/')));
    }

    #[Group('RED-03')]
    public function testTabletRedirect(): void
    {
        $this->boot(['redirect' => self::T]);

        self::assertRedirect('https://t.example.com/?device_view=tablet', 302, $this->handle(self::request('https://www.example.com/', 'ipad_legacy')));
    }

    #[Group('RED-04')]
    public function testNoRedirectOnTheTargetHost(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com/blog/post', 'iphone')));
    }

    #[Group('RED-05')]
    public function testNoRedirectForAViewWithoutOne(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('desktop detector', $this->handle(self::request('http://www.example.com/blog/post')));
    }

    #[Group('RED-06')]
    public function testActionRedirectWithoutPath(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'action' => 'redirect_without_path']]]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post?page=2', 'iphone')));
    }

    #[Group('RED-07')]
    public function testActionNone(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'action' => 'none']]]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/blog/post', 'iphone')));
    }

    #[Group('RED-08')]
    public function testKeepQueryFalse(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'keep_query' => false]]]);

        self::assertRedirect('https://m.example.com/blog/post?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post?page=2', 'iphone')));
    }

    #[Group('RED-09')]
    public function testTheBasePathInTheHostIsKept(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com/shop']]]);

        self::assertRedirect('https://m.example.com/shop/blog/post?page=2&device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post?page=2', 'iphone')));
    }

    public function testABasePathDoesNotRedirectOnItsOwnHost(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com/shop']]]);

        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com/view', 'iphone')));
    }

    #[Group('RED-10')]
    public function testBasePathWithRedirectWithoutPath(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com/shop', 'action' => 'redirect_without_path']]]);

        self::assertRedirect('https://m.example.com/shop/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post', 'iphone')));
    }

    #[Group('RED-11')]
    public function testTheCurrentBaseUrlIsNotCarried(): void
    {
        $this->boot(['redirect' => self::M]);
        $request = Request::create('http://www.example.com/app/blog/post', server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/srv/app/index.php']);
        $request->headers->set('User-Agent', (string) self::userAgent('iphone'));

        self::assertRedirect('https://m.example.com/blog/post?device_view=mobile', 302, $this->handle($request));
    }

    #[Group('RED-12')]
    public function testPortInTheHost(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'http://m.example.com:8080']]]);

        self::assertRedirect('http://m.example.com:8080/?device_view=mobile', 302, $this->handle(self::request('http://m.example.com/', 'iphone')));
    }

    #[Group('RED-13')]
    public function testSameHostAndPort(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'http://m.example.com:8080']]]);

        self::assertPage('mobile detector', $this->handle(self::request('http://m.example.com:8080/view', 'iphone')));
    }

    #[Group('RED-14')]
    #[Group('REG-07')]
    public function testDefaultPortInTheHost(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com:443']]]);

        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com/view', 'iphone')));
        self::assertSame(200, $this->handle(self::request('https://m.example.com/', 'iphone'))->getStatusCode());
        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/', 'iphone')));
    }

    #[Group('RED-15')]
    #[Group('REG-06')]
    public function testHostCaseIsIgnored(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('mobile detector', $this->handle(self::request('https://M.Example.COM/view', 'iphone')));
        self::assertSame(200, $this->handle(self::request('https://M.EXAMPLE.COM/', 'iphone'))->getStatusCode());
    }

    public function testConfiguredHostCaseIsIgnored(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'HTTPS://M.Example.COM']]]);

        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com/view', 'iphone')));
        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/', 'iphone')));
    }

    #[Group('RED-16')]
    #[Group('REG-08')]
    public function testTrailingDotOfTheRequestHostIsIgnored(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com./view', 'iphone')));
        self::assertSame(200, $this->handle(self::request('https://m.example.com./', 'iphone'))->getStatusCode());
    }

    #[Group('RED-17')]
    public function testTrailingDotAndSlashOfTheConfiguredHostAreIgnored(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com./']]]);

        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com/view', 'iphone')));
        self::assertRedirect('https://m.example.com/blog/post?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post', 'iphone')));
    }

    #[Group('REG-09')]
    public function testATrailingSlashInTheConfiguredHostCausesNoLoop(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com/']]]);

        self::assertSame(200, $this->handle(self::request('https://m.example.com/', 'iphone'))->getStatusCode());
        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/', 'iphone')));
    }

    #[Group('RED-18')]
    #[Group('REG-14')]
    public function testStatus301(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'status' => 301]]]);

        $response = $this->handle(self::request('http://www.example.com/', 'iphone'));

        self::assertRedirect('https://m.example.com/?device_view=mobile', 301, $response);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('RED-19')]
    public function testStatus308(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com', 'status' => 308]]]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 308, $this->handle(self::request('http://www.example.com/', 'iphone')));
    }

    #[Group('RED-20')]
    public function testPostIsNotRedirected(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/form', 'iphone', 'POST')));
    }

    #[Group('REG-12')]
    public function testPostIsNeverRedirected(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/form?device_view=desktop', 'iphone', 'POST')));
    }

    #[Group('RED-21')]
    public function testHeadIsRedirected(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('https://m.example.com/form?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/form', 'iphone', 'HEAD')));
    }

    #[Group('RED-22')]
    public function testCustomMethods(): void
    {
        $this->boot(['redirect' => ['methods' => ['GET']] + self::M]);

        $response = $this->handle(self::request('http://www.example.com/form', 'iphone', 'HEAD'));

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
        self::assertRedirect('https://m.example.com/form?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/form', 'iphone')));
    }

    #[Group('RED-23')]
    public function testCacheControlNull(): void
    {
        $this->boot(['redirect' => ['cache_control' => null] + self::M]);

        $response = $this->handle(self::request('http://www.example.com/', 'iphone'));

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $response);
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testCustomCacheControl(): void
    {
        $this->boot(['redirect' => ['cache_control' => 'no-cache, must-revalidate'] + self::M]);

        $response = $this->handle(self::request('http://www.example.com/', 'iphone'));

        self::assertSame('must-revalidate, no-cache, private', $response->headers->get('Cache-Control'));
    }

    #[Group('RED-24')]
    public function testARememberedViewDecidesTheRedirect(): void
    {
        $this->boot(['redirect' => self::D]);

        $response = $this->handle(self::request('https://m.example.com/', 'iphone', cookies: ['device_view' => 'desktop']));

        self::assertRedirect('https://www.example.com/?device_view=desktop', 302, $response);
        self::assertNoCookie($response);
        self::assertSame(['Cookie'], $response->getVary());
    }

    #[Group('RED-25')]
    public function testTheQueryIsEncodedWithRfc3986(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('https://m.example.com/blog/post?q=a%20b&device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post?q=a+b', 'iphone')));
    }

    #[Group('RED-26')]
    public function testHostFromAValidEnvVar(): void
    {
        $_SERVER['DV_MOBILE_HOST'] = 'https://m.example.com';
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => '%env(DV_MOBILE_HOST)%']]]);

        self::assertRedirect('https://m.example.com/blog/post?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/blog/post', 'iphone')));
    }

    #[Group('RED-27')]
    public function testHostFromAnInvalidEnvVar(): void
    {
        $_SERVER['DV_MOBILE_HOST'] = 'm.example.com';
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => '%env(DV_MOBILE_HOST)%']]], ['logger' => TestLogger::class]);

        self::assertPage('mobile detector', $this->handle(self::request('http://www.example.com/view', 'iphone')));
        self::assertSame(['The redirect host "m.example.com" of the mobile view is invalid: expected an absolute http(s) URL without query, fragment or user info. The mobile redirect is disabled.'], $this->logger()->messages(LogLevel::ERROR));
    }

    #[Group('RED-28')]
    public function testCrossHostWarningWithoutACookieDomain(): void
    {
        $this->boot(['redirect' => self::M], ['logger' => TestLogger::class]);

        self::assertSame(302, $this->handle(self::request('http://www.example.com/', 'iphone'))->getStatusCode());
        self::assertSame([self::CROSS_HOST_WARNING], $this->logger()->messages(LogLevel::WARNING));
    }

    public function testTheWarningNamesTheCookieAndThePort(): void
    {
        $this->boot(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'http://m.example.com:8080']], 'remember' => ['cookie' => ['name' => 'dv']]], ['logger' => TestLogger::class]);

        self::assertSame(302, $this->handle(self::request('http://www.example.com/', 'iphone'))->getStatusCode());
        self::assertSame(['The mobile redirect goes to m.example.com:8080, but the dv cookie has no domain, so that host cannot read the remembered view. Set device_view.remember.cookie.domain to a domain both hosts share.'], $this->logger()->messages(LogLevel::WARNING));
    }

    public function testNoWarningWithoutARedirect(): void
    {
        $this->boot(['redirect' => self::M], ['logger' => TestLogger::class]);

        self::assertSame(200, $this->handle(self::request('https://m.example.com/', 'iphone'))->getStatusCode());
        self::assertSame([], $this->logger()->messages(LogLevel::WARNING));
    }

    #[Group('RED-29')]
    public function testNoWarningWithACookieDomain(): void
    {
        $this->boot(['redirect' => self::M, 'remember' => ['cookie' => ['domain' => '.example.com']]], ['logger' => TestLogger::class]);

        self::assertSame(302, $this->handle(self::request('http://www.example.com/', 'iphone'))->getStatusCode());
        self::assertSame([], $this->logger()->messages(LogLevel::WARNING));
    }

    #[Group('RED-30')]
    public function testNoWarningWithoutCookieStorage(): void
    {
        $this->boot(
            ['redirect' => self::M, 'remember' => ['storage' => 'session']],
            ['logger' => TestLogger::class],
            ['session' => ['storage_factory_id' => 'session.storage.factory.mock_file']],
        );

        self::assertSame(302, $this->handle(self::request('http://www.example.com/', 'iphone'))->getStatusCode());
        self::assertSame([], $this->logger()->messages(LogLevel::WARNING));
    }

    #[Group('RED-31')]
    public function testTheHostHeaderNeverReachesLocation(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('https://m.example.com/blog/post?device_view=mobile', 302, $this->handle(self::request('http://evil.example/blog/post', 'iphone')));
    }

    #[Group('REG-11')]
    public function testASpoofedHostNeverReachesLocation(): void
    {
        $this->boot(['redirect' => self::M]);
        $request = self::request('/blog/post', 'iphone');
        $request->headers->set('Host', 'evil.example');

        self::assertRedirect('https://m.example.com/blog/post?device_view=mobile', 302, $this->handle($request));
    }

    #[Group('RED-32')]
    public function testSubRequestsAreNeverRedirected(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertPage('main=mobile sub=mobile detector', $this->handle(self::request('https://m.example.com/with-sub-request?sub=http%3A%2F%2Fwww.example.com%2Fview', 'iphone')));
    }

    #[Group('RED-33')]
    public function testDisabledRedirectsLeaveTheViewLazy(): void
    {
        $this->boot(['detector' => ['id' => 'app.counting_detector']], ['app.counting_detector' => CountingDetector::class]);
        $request = self::request('/', 'iphone');

        $response = $this->handle($request);

        self::assertPage('home page', $response);
        self::assertFalse($response->headers->has('Vary'));
        $detector = $this->service('app.counting_detector');
        self::assertInstanceOf(CountingDetector::class, $detector);
        self::assertSame(0, $detector->calls);
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertNull($context->resolved);
    }

    #[Group('RED-34')]
    public function testEnabledRedirectsResolveTheView(): void
    {
        $this->boot(['redirect' => self::M]);
        $request = self::request('https://m.example.com/', 'iphone');

        $response = $this->handle($request);

        self::assertPage('home page', $response);
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertSame(['Cookie', 'User-Agent'], $context->consultedInputs);
    }

    public function testDisallowedMethodsLeaveTheViewLazy(): void
    {
        $this->boot(['redirect' => self::M]);
        $request = self::request('http://www.example.com/', 'iphone', 'POST');

        self::assertPage('home page', $this->handle($request));
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertNull($context->resolved);
    }

    #[Group('RED-35')]
    public function testTheHostForwardedByATrustedProxyIsTheCurrentHost(): void
    {
        $this->boot(['redirect' => self::M], framework: ['trusted_proxies' => '127.0.0.1', 'trusted_headers' => ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto']]);

        self::assertPage('mobile detector', $this->handle(self::forwarded('127.0.0.1')));
        self::assertRedirect('https://m.example.com/view?device_view=mobile', 302, $this->handle(self::forwarded('203.0.113.9')));
    }

    #[Group('REG-03')]
    public function testNotMobileCannotBypassARedirect(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/?device_view=not_mobile', 'iphone')));
    }

    #[Group('REG-04')]
    public function testAnArrayParameterIsHarmless(): void
    {
        $this->boot(['redirect' => self::M]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/?device_view[]=x', 'iphone')));
    }

    #[Group('REG-10')]
    public function testFollowingRedirectsEnds(): void
    {
        $this->boot(['redirect' => self::M + self::D]);
        $url = 'http://www.example.com/blog/post';
        $cookies = [];
        $redirects = 0;

        for ($i = 0; $i <= 5; ++$i) {
            $response = $this->handle(self::request($url, 'iphone', cookies: $cookies));
            foreach ($response->headers->getCookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
            $location = $response->headers->get('Location');
            if (null === $location) {
                break;
            }
            ++$redirects;
            $url = str_contains($location, '://') ? $location : 'https://m.example.com'.$location;
        }

        self::assertSame(2, $redirects);
        self::assertSame('https://m.example.com/blog/post', $url);
        self::assertPage('mobile remembered', $response);
    }

    #[Group('REG-20')]
    public function testAViewRememberedOnOneHostOnlyCausesNoLoopWhileTheSwitchIsDisabled(): void
    {
        $this->boot(['switch' => ['enabled' => false], 'redirect' => self::M + self::D]);

        [$response, $url, $redirects] = $this->follow('https://m.example.com/view', ['m.example.com' => ['device_view' => 'desktop']]);

        self::assertSame(1, $redirects);
        self::assertSame('https://www.example.com/view?device_view=desktop', $url);
        self::assertPage('mobile detector', $response);
    }

    #[Group('REG-21')]
    public function testAViewRememberedOnOneHostOnlyCausesNoLoopWhileSwitchesAreNotRemembered(): void
    {
        $this->boot(['remember' => ['on_switch' => false], 'redirect' => self::M + self::D]);

        [$response, $url, $redirects] = $this->follow('https://m.example.com/view', ['m.example.com' => ['device_view' => 'desktop']]);

        self::assertSame(1, $redirects);
        self::assertSame('https://www.example.com/view?device_view=desktop', $url);
        self::assertPage('desktop switch', $response);
    }

    public function testAnUnrememberedSwitchToAViewOfTheCurrentHostIsNotRedirected(): void
    {
        $this->boot(['remember' => ['on_switch' => false], 'redirect' => self::M]);

        self::assertPage('mobile switch', $this->handle(self::request('https://m.example.com/view?device_view=mobile')));
        self::assertRedirect('https://m.example.com/view?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/view?device_view=mobile')));
    }

    public function testAnUnrememberedSwitchToAViewWithoutAHostStillRedirects(): void
    {
        $this->boot(['remember' => ['on_switch' => false], 'redirect' => self::M]);

        self::assertRedirect('/view', 302, $this->handle(self::request('https://m.example.com/view?device_view=desktop', 'iphone')));
    }

    #[Group('REG-19')]
    public function testAViewNeverLeaksToTheNextRequest(): void
    {
        $this->boot(['redirect' => self::M + self::D]);

        self::assertRedirect('https://www.example.com/view?device_view=desktop', 302, $this->handle(self::request('https://m.example.com/view?device_view=desktop', 'iphone')));
        self::assertPage('mobile detector', $this->handle(self::request('https://m.example.com/view', 'iphone')));
    }

    #[Group('VW-12')]
    public function testRenamedViewsCrossHostRedirect(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm']], 'redirect' => self::M]);

        self::assertRedirect('https://m.example.com/blog/post?device_view=m', 302, $this->handle(self::request('http://www.example.com/blog/post', 'iphone')));
    }

    public function testTabletAsMobileRedirectsATabletToTheMobileHost(): void
    {
        $this->boot(['tablet_as_mobile' => true, 'redirect' => self::M + self::T]);

        self::assertRedirect('https://m.example.com/?device_view=mobile', 302, $this->handle(self::request('http://www.example.com/', 'ipad_legacy')));
    }

    public function testASwitchWithoutRedirectGoesToTheHostOfItsView(): void
    {
        $this->boot(['switch' => ['target' => 'none'], 'redirect' => self::M]);

        $response = $this->handle(self::request('http://www.example.com/view?device_view=mobile'));

        self::assertRedirect('https://m.example.com/view?device_view=mobile', 302, $response);
        self::assertSetCookie(self::sc('mobile'), $response);
    }

    /**
     * Follows each Location from a GET of the URL with an iPhone, at most 5 times, sending each
     * host only its own cookies, as a browser does with cookies without a domain.
     *
     * @param array<string, array<string, string>> $jars the cookies of each host
     *
     * @return array{Response, string, int} the last response, its URL and the number of redirects
     */
    private function follow(string $url, array $jars): array
    {
        $redirects = 0;
        for ($i = 0;; ++$i) {
            $host = (string) parse_url($url, \PHP_URL_HOST);
            $response = $this->handle(self::request($url, 'iphone', cookies: $jars[$host] ?? []));
            foreach ($response->headers->getCookies() as $cookie) {
                $jars[$host][$cookie->getName()] = (string) $cookie->getValue();
            }
            $location = $response->headers->get('Location');
            if (null === $location || 5 === $i) {
                return [$response, $url, $redirects];
            }
            ++$redirects;
            $url = str_contains($location, '://') ? $location : parse_url($url, \PHP_URL_SCHEME).'://'.$host.$location;
        }
    }

    /**
     * GET http://10.0.0.5/view from $remoteAddress, forwarded for https://m.example.com.
     */
    private static function forwarded(string $remoteAddress): Request
    {
        $request = Request::create('http://10.0.0.5/view', server: ['REMOTE_ADDR' => $remoteAddress]);
        $request->headers->set('User-Agent', (string) self::userAgent('iphone'));
        $request->headers->set('X-Forwarded-For', '198.51.100.7');
        $request->headers->set('X-Forwarded-Host', 'm.example.com');
        $request->headers->set('X-Forwarded-Proto', 'https');

        return $request;
    }

    private static function assertRedirect(string $location, int $status, Response $response): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($location, $response->headers->get('Location'));
    }

    private static function assertPage(string $body, Response $response): void
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($body, $response->getContent());
        self::assertFalse($response->headers->has('Location'));
        self::assertSame([], $response->headers->getCookies());
    }

    private function logger(): TestLogger
    {
        $logger = $this->service('logger');
        self::assertInstanceOf(TestLogger::class, $logger);

        return $logger;
    }
}
