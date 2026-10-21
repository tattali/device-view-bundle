<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * The trusted header (trusted_header), through the kernel: a proxy or CDN tells the view, after
 * the remembered view and before the detector.
 *
 * The TH config of docs/scenarios.md trusts the proxy 127.0.0.1, the REMOTE_ADDR of
 * Request::create().
 */
final class TrustedHeaderTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array TH = ['trusted_header' => ['name' => 'X-Device-View']];

    private const array TRUSTED_PROXY = ['trusted_proxies' => '127.0.0.1'];

    private const string UNTRUSTED = '203.0.113.9';

    #[Group('TH-01')]
    #[Group('RES-07')]
    public function testTheHeaderOfATrustedProxyBeatsTheDetector(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);
        $request = self::withHeader(self::request('/view', 'iphone'), 'tablet');

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet trusted_header', $response->getContent());
        self::assertSame(['Cookie', 'X-Device-View'], $response->getVary());
        self::assertSame(ViewSource::TrustedHeader, self::context($request)->resolved?->source);
        self::assertSame([], $response->headers->getCookies());
    }

    #[Group('VARY-05')]
    public function testTheTrustedHeaderIsListedInVary(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view'), 'tablet'));

        self::assertSame(['Cookie', 'X-Device-View'], $response->getVary());
    }

    #[Group('TH-06')]
    public function testAnAbsentHeaderIsListedInVary(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'X-Device-View', 'User-Agent'], $response->getVary());
    }

    #[Group('TH-02')]
    public function testTheHeaderOfAnUntrustedClientIsIgnored(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view', 'iphone'), 'tablet', self::UNTRUSTED));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    public function testNoClientIsTrustedWithoutTrustedProxies(): void
    {
        $this->boot(self::TH);

        $response = $this->handle(self::withHeader(self::request('/view', 'iphone'), 'tablet'));

        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('TH-03')]
    public function testAnyClientIsReadWhenTrustedProxiesAreNotRequired(): void
    {
        $this->boot(['trusted_header' => ['name' => 'X-Device-View', 'trusted_proxies_only' => false]], framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view', 'iphone'), 'tablet', self::UNTRUSTED));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet trusted_header', $response->getContent());
        self::assertSame(['Cookie', 'X-Device-View'], $response->getVary());
    }

    #[Group('TH-04')]
    public function testTheValueIsTrimmedAndLowerCased(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view'), ' Tablet '));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet trusted_header', $response->getContent());
    }

    #[Group('TH-05')]
    public function testTheHeaderIsNeverReadWhenNotConfigured(): void
    {
        $this->boot(framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view', 'iphone'), 'tablet'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('VAL-13')]
    public function testAnInvalidValueIsIgnored(): void
    {
        $this->boot(self::TH, ['logger' => TestLogger::class], self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view', 'iphone'), 'phone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'X-Device-View', 'User-Agent'], $response->getVary());
        self::assertSame([], $response->headers->getCookies());
        self::assertSame(['Ignored an invalid device view from the trusted header.'], $this->logger()->messages(LogLevel::DEBUG));
    }

    #[Group('RES-04')]
    public function testAForcedViewBeatsEverySource(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);
        $request = self::withHeader(self::request('/set-view/tablet', 'iphone', cookies: ['device_view' => 'mobile']), 'desktop');

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet forced', $response->getContent());
        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Vary'));
        self::assertSame([], self::context($request)->consultedInputs);
    }

    #[Group('RES-06')]
    public function testTheRememberedViewBeatsTheTrustedHeader(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view', cookies: ['device_view' => 'mobile']), 'desktop'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile remembered', $response->getContent());
        self::assertSame(['Cookie'], $response->getVary());
    }

    #[Group('VW-13')]
    public function testARenamedView(): void
    {
        $this->boot(['views' => ['names' => ['tablet' => 't']]] + self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view'), 't'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('t trusted_header', $response->getContent());
    }

    #[Group('REM-16')]
    public function testATrustedHeaderViewIsRememberedAsDetected(): void
    {
        $this->boot(['remember' => ['detected_view' => true]] + self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view'), 'tablet'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet trusted_header', $response->getContent());
        self::assertSetCookie(self::sc('tablet'), $response);
    }

    public function testATrustedHeaderViewIsNotRememberedByDefault(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view'), 'tablet'));

        self::assertSame([], $response->headers->getCookies());
    }

    public function testTheHeaderIsReadOnlyWhenTheViewIsUsed(): void
    {
        $this->boot(self::TH, framework: self::TRUSTED_PROXY);
        $request = self::withHeader(self::request('/', 'iphone'), 'tablet');

        $response = $this->handle($request);

        self::assertSame('home page', $response->getContent());
        self::assertFalse($response->headers->has('Vary'));
        self::assertNull(self::context($request)->resolved);
    }

    /**
     * The trusted header comes after the switch parameter, like the remembered view.
     */
    public function testTheSwitchBeatsTheTrustedHeader(): void
    {
        $this->boot(['switch' => ['target' => 'none']] + self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view?device_view=mobile'), 'tablet'));

        self::assertSame('mobile switch', $response->getContent());
        self::assertFalse($response->headers->has('Vary'));
    }

    public function testTabletAsMobileMapsATrustedHeaderTablet(): void
    {
        $this->boot(['tablet_as_mobile' => true] + self::TH, framework: self::TRUSTED_PROXY);

        $response = $this->handle(self::withHeader(self::request('/view'), 'tablet'));

        self::assertSame('mobile trusted_header', $response->getContent());
    }

    private static function withHeader(Request $request, string $value, ?string $remoteAddress = null): Request
    {
        $request->headers->set('X-Device-View', $value);
        if (null !== $remoteAddress) {
            $request->server->set('REMOTE_ADDR', $remoteAddress);
        }

        return $request;
    }

    private static function context(Request $request): DeviceViewContext
    {
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);

        return $context;
    }

    private function logger(): TestLogger
    {
        $logger = $this->service('logger');
        self::assertInstanceOf(TestLogger::class, $logger);

        return $logger;
    }
}
