<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Tests\App\Detector\CountingDetector;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\Tests\App\Event\ViewResolvedListener;
use DeviceViewBundle\Tests\App\RequestRecorder;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The lazy resolution of the view for each request, through the kernel.
 */
final class ResolutionTest extends TestCase
{
    use HandlesRequests;

    private const array COUNTING = ['detector' => ['id' => 'app.counting_detector']];

    private const array COUNTING_SERVICE = ['app.counting_detector' => CountingDetector::class];

    #[Group('RES-10')]
    public function testTheContextLivesInTheMainRequest(): void
    {
        $request = self::request('/view', 'iphone');

        $response = $this->handle($request);

        self::assertSame('mobile detector', $response->getContent());
        $context = $request->attributes->get('_device_view');
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertFalse($context->skipped);
        self::assertSame(DeviceType::Mobile, $context->resolved?->view);
        self::assertSame(ViewSource::Detector, $context->resolved->source);
        self::assertSame(['Cookie', 'User-Agent'], $context->consultedInputs);
    }

    #[Group('RES-01')]
    public function testResolutionIsLazy(): void
    {
        $this->boot(self::COUNTING, self::COUNTING_SERVICE);
        $request = self::request('/', 'iphone');

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('home page', $response->getContent());
        self::assertSame(0, $this->detector()->calls);
        self::assertFalse($response->headers->has('Vary'));
        self::assertSame([], $response->headers->getCookies());
        $context = $request->attributes->get('_device_view');
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertNull($context->resolved);
        self::assertSame([], $this->viewResolvedListener()->events, 'ViewResolvedEvent is not dispatched');
    }

    public function testTheViewIsResolvedOncePerRequest(): void
    {
        $this->boot(self::COUNTING, self::COUNTING_SERVICE);

        $response = $this->handle(self::request('/readers/mobile', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->detector()->calls);
    }

    #[Group('RES-02')]
    public function testTheDetectorDecides(): void
    {
        $response = $this->handle(self::request('/view', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('RES-03')]
    public function testTheDefaultViewAppliesWhenTheDetectorCannotTell(): void
    {
        $response = $this->handle(self::request('/view', 'empty'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop default', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('DET-MD-04')]
    public function testTheConfiguredMobileDetectHeadersArePassedAndListedInVary(): void
    {
        $this->boot(['detector' => ['mobile_detect' => ['headers' => ['User-Agent', 'X-Device-User-Agent']]]]);
        $request = self::request('/view');
        $request->headers->set('X-Device-User-Agent', self::userAgent('iphone'));

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent', 'X-Device-User-Agent'], $response->getVary());
    }

    #[Group('DET-MD-05')]
    public function testHeadersThatAreNotConfiguredAreNotPassed(): void
    {
        $request = self::request('/view');
        $request->headers->set('X-Device-User-Agent', self::userAgent('iphone'));

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    public function testAForcedViewBeatsTheDetector(): void
    {
        $this->boot(self::COUNTING, self::COUNTING_SERVICE);
        $request = self::request('/set-view/tablet', 'iphone');

        $response = $this->handle($request);

        self::assertSame('tablet forced', $response->getContent());
        self::assertSame(0, $this->detector()->calls);
        self::assertFalse($response->headers->has('Vary'));
        self::assertSame([], $response->headers->getCookies());
        $context = $request->attributes->get('_device_view');
        self::assertInstanceOf(DeviceViewContext::class, $context);
        self::assertSame([], $context->consultedInputs);
    }

    public function testTabletAsMobileMapsAForcedTablet(): void
    {
        $this->boot(['tablet_as_mobile' => true]);

        self::assertSame('mobile forced', $this->handle(self::request('/set-view/tablet'))->getContent());
    }

    #[Group('FAC-13')]
    #[Group('RES-09')]
    public function testSubRequestsReadTheMainRequestView(): void
    {
        $this->boot(self::COUNTING, self::COUNTING_SERVICE);

        $response = $this->handle(self::request('/with-sub-request', 'iphone'));

        self::assertSame('main=mobile sub=mobile detector', $response->getContent());
        self::assertSame(1, $this->detector()->calls);
        $recorder = $this->service(RequestRecorder::class);
        self::assertInstanceOf(RequestRecorder::class, $recorder);
        self::assertCount(2, $recorder->requests);
        self::assertInstanceOf(DeviceViewContext::class, $recorder->requests[0]->attributes->get('_device_view'));
        self::assertFalse($recorder->requests[1]->attributes->has('_device_view'), 'the sub-request has no context of its own');
        self::assertCount(1, $this->viewResolvedListener()->events, 'ViewResolvedEvent is dispatched once');
    }

    #[Group('VW-01')]
    public function testDefaultViewMobile(): void
    {
        $this->boot(['default_view' => 'mobile']);

        self::assertSame('mobile default', $this->handle(self::request('/view', 'empty'))->getContent());
    }

    #[Group('VW-02')]
    public function testDefaultViewTabletWithoutUserAgent(): void
    {
        $this->boot(['default_view' => 'tablet']);

        self::assertSame('tablet default', $this->handle(self::request('/view', 'missing'))->getContent());
    }

    #[Group('VW-03')]
    public function testTabletAsMobileMapsADetectedTablet(): void
    {
        $this->boot(['tablet_as_mobile' => true]);

        self::assertSame('mobile detector', $this->handle(self::request('/view', 'ipad_legacy'))->getContent());
    }

    #[Group('VW-06')]
    public function testTabletAsMobileMapsATabletDefaultView(): void
    {
        $this->boot(['tablet_as_mobile' => true, 'default_view' => 'tablet']);

        self::assertSame('mobile default', $this->handle(self::request('/view', 'empty'))->getContent());
    }

    #[Group('VW-07')]
    public function testTabletAsMobileLeavesOtherViewsAlone(): void
    {
        $this->boot(['tablet_as_mobile' => true]);

        self::assertSame('desktop detector', $this->handle(self::request('/view', 'desktop_chrome'))->getContent());
    }

    public function testATabletStaysATabletByDefault(): void
    {
        self::assertSame('tablet detector', $this->handle(self::request('/view', 'ipad_legacy'))->getContent());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function userAgentExpectations(): iterable
    {
        foreach (self::userAgentFixtures() as $name => $fixture) {
            $expected = $fixture['expected']['mobile_detect'];
            yield $name => [$name, null === $expected ? 'desktop default' : $expected.' detector'];
        }
    }

    #[Group('DET-MD-01')]
    #[DataProvider('userAgentExpectations')]
    public function testEveryUserAgentFixture(string $fixture, string $expected): void
    {
        self::assertSame($expected, $this->handle(self::request('/view', $fixture))->getContent());
    }

    #[Group('DET-MD-02')]
    public function testTheDetectorReadsTheRequestNotServer(): void
    {
        $before = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $_SERVER['HTTP_USER_AGENT'] = self::userAgent('iphone');

        try {
            $response = $this->handle(self::request('/view', 'desktop_chrome'));
        } finally {
            if (null === $before) {
                unset($_SERVER['HTTP_USER_AGENT']);
            } else {
                $_SERVER['HTTP_USER_AGENT'] = $before;
            }
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
    }

    #[Group('DET-MD-03')]
    public function testEachRequestIsDetectedAfresh(): void
    {
        $this->boot();

        self::assertSame('mobile detector', $this->handle(self::request('/view', 'iphone'))->getContent());
        self::assertSame('desktop detector', $this->handle(self::request('/view', 'desktop_chrome'))->getContent());
        self::assertSame('tablet detector', $this->handle(self::request('/view', 'ipad_legacy'))->getContent());
    }

    #[Group('DET-CUS-02')]
    public function testACustomDetectorAnsweringNullGivesTheDefaultView(): void
    {
        $this->boot(['detector' => ['id' => 'app.header_detector']], ['app.header_detector' => HeaderDetector::class]);

        self::assertSame('desktop default', $this->handle(self::request('/view'))->getContent());
    }

    #[Group('DET-CUS-01')]
    public function testACustomDetectorDecides(): void
    {
        $this->boot(['detector' => ['id' => 'app.header_detector']], ['app.header_detector' => HeaderDetector::class]);
        $request = self::request('/view');
        $request->headers->set('X-Test-View', 'tablet');

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet detector', $response->getContent());
        self::assertSame(['Cookie', 'X-Test-View'], $response->getVary());
    }

    private function detector(): CountingDetector
    {
        $detector = $this->service('app.counting_detector');
        self::assertInstanceOf(CountingDetector::class, $detector);

        return $detector;
    }

    private function viewResolvedListener(): ViewResolvedListener
    {
        $listener = $this->service(ViewResolvedListener::class);
        self::assertInstanceOf(ViewResolvedListener::class, $listener);

        return $listener;
    }
}
