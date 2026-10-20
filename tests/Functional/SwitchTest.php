<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Tests\App\Detector\CountingDetector;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The switch query parameter, through the kernel: the redirect it answers with, and the view it
 * gives the request.
 *
 * The remember cookie a switch writes comes with the remember storage, so the scenarios that
 * also expect a Set-Cookie stay planned; the tests without a group check the rest of them.
 */
final class SwitchTest extends TestCase
{
    use HandlesRequests;

    private const string INVALID_SWITCH = 'Ignored an invalid device view from the switch parameter.';

    public function testSwitchToTheCurrentPage(): void
    {
        $request = self::request('/view?device_view=mobile');

        $response = $this->handle($request);

        self::assertRedirect('/view', 302, $response);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertFalse($response->headers->has('Vary'));
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertTrue($context->switched);
        self::assertSame(DeviceType::Mobile, $context->switchView);
    }

    #[Group('SW-02')]
    public function testOtherQueryParametersAreKeptInOrder(): void
    {
        self::assertRedirect('/view?b=2&a=1', 302, $this->handle(self::request('/view?b=2&device_view=mobile&a=1')));
    }

    #[Group('SW-03')]
    public function testTheQueryIsRebuiltWithRfc3986Encoding(): void
    {
        self::assertRedirect('/view?q=a%20b&tags%5B0%5D=x', 302, $this->handle(self::request('/view?q=a+b&tags[]=x&device_view=mobile')));
    }

    #[Group('SW-04')]
    public function testTheBaseUrlIsKept(): void
    {
        self::assertRedirect('/app/view', 302, $this->handle(self::appRequest('/app/view?device_view=mobile')));
    }

    public function testTargetHome(): void
    {
        $this->boot(['switch' => ['target' => 'home']]);

        self::assertRedirect('/', 302, $this->handle(self::request('/view?a=1&device_view=mobile')));
    }

    #[Group('SW-06')]
    public function testTargetHomeKeepsTheBaseUrl(): void
    {
        $this->boot(['switch' => ['target' => 'home']]);

        self::assertRedirect('/app/', 302, $this->handle(self::appRequest('/app/view?device_view=mobile')));
    }

    public function testTargetNoneAppliesToThisRequestOnly(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);
        $request = self::request('/view?device_view=mobile');

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile switch', $response->getContent());
        self::assertFalse($response->headers->has('Location'));
        self::assertFalse($response->headers->has('Vary'));
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertTrue($context->switched, 'the remember step needs to know the switch applied');
    }

    public function testTheSwitchConsultsNoOtherInput(): void
    {
        $this->boot(['switch' => ['target' => 'none'], 'detector' => ['id' => 'app.counting_detector']], ['app.counting_detector' => CountingDetector::class]);
        $request = self::request('/view?device_view=tablet', 'iphone');

        self::assertSame('tablet switch', $this->handle($request)->getContent());
        $detector = $this->service('app.counting_detector');
        self::assertInstanceOf(CountingDetector::class, $detector);
        self::assertSame(0, $detector->calls);
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertSame(ViewSource::Switch, $context->resolved?->source);
        self::assertSame([], $context->consultedInputs);
    }

    public function testTabletAsMobileMapsASwitchToTablet(): void
    {
        $this->boot(['tablet_as_mobile' => true, 'switch' => ['target' => 'none']]);

        self::assertSame('mobile switch', $this->handle(self::request('/view?device_view=tablet'))->getContent());
    }

    public function testAForcedViewBeatsTheSwitch(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        self::assertSame('desktop forced', $this->handle(self::request('/set-view/desktop?device_view=mobile'))->getContent());
    }

    #[Group('SW-08')]
    public function testStatus303(): void
    {
        $this->boot(['switch' => ['status' => 303]]);

        self::assertRedirect('/view', 303, $this->handle(self::request('/view?device_view=mobile')));
    }

    #[Group('SW-09')]
    public function testStatus307(): void
    {
        $this->boot(['switch' => ['status' => 307]]);

        self::assertRedirect('/view', 307, $this->handle(self::request('/view?device_view=mobile')));
    }

    public function testCustomParameter(): void
    {
        $this->boot(['switch' => ['parameter' => 'view', 'target' => 'none']]);

        self::assertSame('tablet switch', $this->handle(self::request('/view?view=tablet'))->getContent());
        $this->boot(['switch' => ['parameter' => 'view']]);
        self::assertRedirect('/view?device_view=x', 302, $this->handle(self::request('/view?view=tablet&device_view=x')));
    }

    #[Group('SW-11')]
    public function testCustomParameterTheDefaultNameIsIgnored(): void
    {
        $this->boot(['switch' => ['parameter' => 'view']]);

        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view=tablet')));
    }

    #[Group('SW-12')]
    #[Group('RES-11')]
    public function testPostIsNotSwitched(): void
    {
        self::assertPage('desktop detector', $this->handle(self::request('/form?device_view=mobile', method: 'POST')));
    }

    public function testHeadIsSwitched(): void
    {
        self::assertRedirect('/form', 302, $this->handle(self::request('/form?device_view=mobile', method: 'HEAD')));
    }

    public function testRedirectMethodsAlsoGovernTheSwitch(): void
    {
        $this->boot(['redirect' => ['methods' => ['GET', 'POST']]]);

        self::assertRedirect('/form', 302, $this->handle(self::request('/form?device_view=mobile', method: 'POST')));
        $head = $this->handle(self::request('/form?device_view=mobile', method: 'HEAD'));
        self::assertSame(200, $head->getStatusCode());
        self::assertFalse($head->headers->has('Location'));
    }

    public function testRedirectMethodsAreCaseInsensitive(): void
    {
        $this->boot(['redirect' => ['methods' => ['get', 'Post']]]);

        self::assertRedirect('/form', 302, $this->handle(self::request('/form?device_view=mobile', method: 'POST')));
        self::assertRedirect('/form', 302, $this->handle(self::request('/form?device_view=mobile')));
    }

    public function testCacheControlNullSetsNothing(): void
    {
        $this->boot(['redirect' => ['cache_control' => null]]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertRedirect('/view', 302, $response);
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'), 'Symfony\'s computed value: nothing is stored');
    }

    #[Group('SW-16')]
    public function testCustomCacheControl(): void
    {
        $this->boot(['redirect' => ['cache_control' => 'no-cache, no-store, must-revalidate']]);

        $response = $this->handle(self::request('/view?device_view=mobile'));

        self::assertRedirect('/view', 302, $response);
        self::assertSame('must-revalidate, no-cache, no-store, private', $response->headers->get('Cache-Control'));
    }

    #[Group('SW-22')]
    public function testSubRequestsNeverSwitch(): void
    {
        $response = $this->handle(self::request('/with-sub-request?sub=%2Fview%3Fdevice_view%3Dtablet', 'iphone'));

        self::assertPage('main=mobile sub=mobile detector', $response);
    }

    public function testASubRequestSeesTheSwitchOfItsMainRequest(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        self::assertPage('main=tablet sub=tablet switch', $this->handle(self::request('/with-sub-request?device_view=tablet', 'iphone')));
    }

    public function testDisabledSwitchIsNeverRead(): void
    {
        $this->boot(['switch' => ['enabled' => false]], ['logger' => TestLogger::class]);

        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view=phone')));
        self::assertSame([], $this->logger()->messages(LogLevel::DEBUG));
    }

    #[Group('RES-12')]
    public function testTheResolverIgnoresTheSwitchWhenItIsDisabled(): void
    {
        $this->boot(['switch' => ['enabled' => false]]);

        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view=mobile')));
    }

    #[Group('VAL-02')]
    public function testAnUnknownSwitchWordIsIgnored(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);

        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view=phone')));
        self::assertSame([self::INVALID_SWITCH], $this->logger()->messages(LogLevel::DEBUG), 'logged once, though the listener and the resolver both read it');
    }

    #[Group('VAL-03')]
    public function testNotMobileIsIgnored(): void
    {
        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view=not_mobile')));
    }

    #[Group('VAL-04')]
    public function testAnArraySwitchValueIsIgnored(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);

        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view[]=mobile')));
        self::assertSame([self::INVALID_SWITCH], $this->logger()->messages(LogLevel::DEBUG));
    }

    #[Group('VAL-05')]
    public function testAnEmptySwitchValueIsIgnored(): void
    {
        self::assertPage('desktop detector', $this->handle(self::request('/view?device_view=')));
    }

    #[Group('VAL-06')]
    public function testTheSwitchTokenMustBeAConfiguredName(): void
    {
        $this->boot(['views' => ['names' => ['desktop' => 'd']]]);

        self::assertPage('d detector', $this->handle(self::request('/view?device_view=desktop')));
    }

    #[Group('VW-09')]
    public function testRenamedViewsTheCanonicalNameIsNoLongerAToken(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd']]]);

        self::assertPage('d detector', $this->handle(self::request('/view?device_view=mobile')));
        $this->boot(['views' => ['names' => ['mobile' => 'm', 'tablet' => 't', 'desktop' => 'd']], 'switch' => ['target' => 'none']]);
        self::assertSame('t switch', $this->handle(self::request('/view?device_view=t'))->getContent());
    }

    public function testTheSwitchTokenIsTrimmedAndLowerCased(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        self::assertSame('mobile switch', $this->handle(self::request('/view?device_view=%20Mobile%20'))->getContent());
    }

    public function testAValidSwitchIsNotLogged(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);

        self::assertRedirect('/view', 302, $this->handle(self::request('/view?device_view=mobile')));
        self::assertSame([], $this->logger()->messages(LogLevel::DEBUG));
    }

    public function testASwitchDoesNotLeakToTheNextRequest(): void
    {
        $this->boot(['switch' => ['target' => 'none']]);

        self::assertSame('tablet switch', $this->handle(self::request('/view?device_view=tablet', 'iphone'))->getContent());
        self::assertSame('mobile detector', $this->handle(self::request('/view', 'iphone'))->getContent());
    }

    /**
     * A request through the front controller /app (docs/scenarios.md, Conventions), with the
     * desktop_chrome user agent.
     */
    private static function appRequest(string $uri): Request
    {
        $request = Request::create('http://localhost'.$uri, server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/srv/app/index.php']);
        $request->headers->set('User-Agent', (string) self::userAgent('desktop_chrome'));

        return $request;
    }

    private static function assertRedirect(string $location, int $status, Response $response): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($location, $response->headers->get('Location'));
        self::assertSame([], $response->headers->getCookies());
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
