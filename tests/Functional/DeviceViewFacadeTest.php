<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\DeviceView;
use DeviceViewBundle\Tests\App\Detector\CountingDetector;
use DeviceViewBundle\Tests\App\TestLogger;
use DeviceViewBundle\View\DeviceType;
use DeviceViewBundle\View\ViewSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * The DeviceView facade, through the kernel.
 */
final class DeviceViewFacadeTest extends TestCase
{
    use HandlesRequests;

    #[Group('FAC-01')]
    public function testReaders(): void
    {
        $this->boot();

        self::assertSame([
            'view' => 'mobile',
            'name' => 'mobile',
            'mobile' => true,
            'tablet' => false,
            'desktop' => false,
            'is' => true,
            'is_mobile_type' => true,
            'source' => 'detector',
        ], $this->readers('/readers/mobile', 'iphone'));
        self::assertFalse($this->readers('/readers/tablet', 'iphone')['is']);
    }

    public function testReadersOfATablet(): void
    {
        self::assertSame([
            'view' => 'tablet',
            'name' => 'tablet',
            'mobile' => false,
            'tablet' => true,
            'desktop' => false,
            'is' => true,
            'is_mobile_type' => false,
            'source' => 'detector',
        ], $this->readers('/readers/tablet', 'ipad_legacy'));
    }

    public function testReadersOfADesktop(): void
    {
        self::assertSame([
            'view' => 'desktop',
            'name' => 'desktop',
            'mobile' => false,
            'tablet' => false,
            'desktop' => true,
            'is' => false,
            'is_mobile_type' => false,
            'source' => 'default',
        ], $this->readers('/readers/mobile', 'empty'));
    }

    #[Group('FAC-02')]
    public function testRenamedViewName(): void
    {
        $this->boot(['views' => ['names' => ['mobile' => 'm']]]);

        $readers = $this->readers('/readers/m', 'iphone');

        self::assertSame('m', $readers['name']);
        self::assertTrue($readers['is']);
        self::assertSame('mobile', $readers['view']);
    }

    #[Group('FAC-03')]
    public function testOutsideARequest(): void
    {
        $this->boot(['default_view' => 'tablet', 'detector' => ['id' => 'app.counting_detector']], ['app.counting_detector' => CountingDetector::class]);
        $deviceView = $this->deviceView();

        self::assertSame(DeviceType::Tablet, $deviceView->getView());
        self::assertSame(ViewSource::Default, $deviceView->getSource());
        self::assertSame('tablet', $deviceView->getViewName());
        self::assertTrue($deviceView->isTablet());
        $detector = $this->service('app.counting_detector');
        self::assertInstanceOf(CountingDetector::class, $detector);
        self::assertSame(0, $detector->calls);
    }

    public function testOutsideARequestTabletAsMobileAppliesToTheDefaultView(): void
    {
        $this->boot(['default_view' => 'tablet', 'tablet_as_mobile' => true]);

        self::assertSame(DeviceType::Mobile, $this->deviceView()->getView());
        self::assertSame(ViewSource::Default, $this->deviceView()->getSource());
    }

    #[Group('FAC-04')]
    public function testSetViewOutsideARequest(): void
    {
        $this->boot(services: ['logger' => TestLogger::class]);
        $deviceView = $this->deviceView();

        $deviceView->setView('mobile');

        self::assertSame(DeviceType::Desktop, $deviceView->getView());
        $logger = $this->service('logger');
        self::assertInstanceOf(TestLogger::class, $logger);
        self::assertSame(['Ignored DeviceView::setView(): there is no request.'], $logger->messages(LogLevel::DEBUG));
    }

    #[Group('FAC-05')]
    public function testSetViewWithAnUnknownViewName(): void
    {
        $this->boot();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown device view "phone". Expected one of: mobile, tablet, desktop.');

        $this->handle(self::request('/set-view/phone'), false);
    }

    #[Group('FAC-05')]
    public function testIsWithAnUnknownViewName(): void
    {
        $this->boot();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown device view "phone". Expected one of: mobile, tablet, desktop.');

        $this->handle(self::request('/readers/phone'), false);
    }

    public function testAnUnknownViewNameThrowsOutsideARequestToo(): void
    {
        $this->boot();
        $deviceView = $this->deviceView();

        try {
            $deviceView->is('phone');
            self::fail('is() accepted an unknown view name.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Unknown device view "phone". Expected one of: mobile, tablet, desktop.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown device view "phone". Expected one of: mobile, tablet, desktop.');

        $deviceView->setView('phone');
    }

    public function testSetViewTakesAConfiguredName(): void
    {
        $this->boot(['views' => ['names' => ['tablet' => 't']]]);

        self::assertSame('t forced', $this->handle(self::request('/set-view/t'))->getContent());
    }

    #[Group('FAC-14')]
    public function testAutowiring(): void
    {
        $this->boot();

        self::assertInstanceOf(DeviceView::class, $this->service('test.device_view.device_view'));
        self::assertSame($this->service('test.device_view.device_view'), $this->service('test.device_view'));
    }

    private function deviceView(): DeviceView
    {
        $deviceView = $this->service('test.device_view');
        self::assertInstanceOf(DeviceView::class, $deviceView);

        return $deviceView;
    }

    /**
     * @return array<mixed>
     */
    private function readers(string $path, string $userAgent): array
    {
        $response = $this->handle(self::request($path, $userAgent));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $readers = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($readers);

        return $readers;
    }
}
