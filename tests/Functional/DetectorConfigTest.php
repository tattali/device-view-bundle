<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Detector\DeviceDetectorInterface;
use DeviceViewBundle\Detector\MobileDetectDetector;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\Tests\App\DetectorCache;
use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\View\DeviceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class DetectorConfigTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    #[Group('CFG-52')]
    public function testTheDefaultsUseMobileDetectWhenItIsInstalled(): void
    {
        $detector = $this->detector([]);

        self::assertInstanceOf(MobileDetectDetector::class, $detector);
        self::assertSame(['User-Agent'], $detector->varyHeaders());
        self::assertSame(DeviceType::Mobile, $detector->detect(self::request('iphone')));
    }

    public function testAutoUsesMobileDetectWhenItIsInstalled(): void
    {
        self::assertInstanceOf(MobileDetectDetector::class, $this->detector(['detector' => ['id' => 'auto']]));
    }

    public function testMobileDetectCanBeNamed(): void
    {
        self::assertInstanceOf(MobileDetectDetector::class, $this->detector(['detector' => ['id' => 'mobile_detect']]));
    }

    public function testTheInterfaceAliasIsTheDetector(): void
    {
        $container = $this->boot([]);

        self::assertSame($container->get('test.device_view.detector'), $container->get('test.device_detector_interface'));
    }

    public function testTheMobileDetectHeadersAreConfigurable(): void
    {
        $detector = $this->detector(['detector' => ['mobile_detect' => ['headers' => ['User-Agent', 'X-Device-User-Agent']]]]);
        $request = self::request('desktop_chrome');
        $request->headers->set('X-Device-User-Agent', self::userAgent('iphone'));

        self::assertSame(['User-Agent', 'X-Device-User-Agent'], $detector->varyHeaders());
        self::assertSame(DeviceType::Mobile, $detector->detect($request));
    }

    public function testTheMobileDetectCacheIsTheConfiguredService(): void
    {
        $container = $this->boot(
            ['detector' => ['mobile_detect' => ['cache' => 'app.detector_cache']]],
            ['app.detector_cache' => DetectorCache::class],
        );
        $detector = $container->get('test.device_view.detector');
        $cache = $container->get('app.detector_cache');
        self::assertInstanceOf(DeviceDetectorInterface::class, $detector);
        self::assertInstanceOf(DetectorCache::class, $cache);

        self::assertSame(DeviceType::Mobile, $detector->detect(self::request('iphone')));
        self::assertNotSame([], $cache->pool->getValues());
    }

    public function testTheMobileDetectCacheCanBeNull(): void
    {
        self::assertInstanceOf(MobileDetectDetector::class, $this->detector(['detector' => ['mobile_detect' => ['cache' => null]]]));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidCaches(): iterable
    {
        yield 'empty string' => ['', '""'];
        yield 'integer' => [12, '12'];
        yield 'boolean' => [true, 'true'];
    }

    #[DataProvider('invalidCaches')]
    public function testTheMobileDetectCacheIsNullOrAServiceId(mixed $cache, string $shown): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.detector.mobile_detect.cache must be null or a service id, got '.$shown.'.');

        $this->boot(['detector' => ['mobile_detect' => ['cache' => $cache]]]);
    }

    #[Group('DET-CUS-03')]
    public function testACustomDetectorServiceIsTheDetector(): void
    {
        $container = $this->boot(
            ['detector' => ['id' => 'app.header_detector']],
            ['app.header_detector' => HeaderDetector::class],
        );

        $custom = $container->get('app.header_detector');
        self::assertInstanceOf(HeaderDetector::class, $custom);
        self::assertSame($custom, $container->get('test.device_view.detector'));
        self::assertSame($custom, $container->get('test.device_detector_interface'));
    }

    #[Group('CFG-07')]
    public function testAMissingDetectorServiceFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.nope" set in device_view.detector.id does not exist.');

        $this->boot(['detector' => ['id' => 'app.nope']]);
    }

    #[Group('CFG-08')]
    public function testADetectorServiceOfAnotherClassFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.not_a_detector" set in device_view.detector.id must implement DeviceViewBundle\Detector\DeviceDetectorInterface.');

        $this->boot(['detector' => ['id' => 'app.not_a_detector']], ['app.not_a_detector' => \stdClass::class]);
    }

    public function testAnEmptyDetectorIdFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.detector.id" cannot contain an empty value');

        $this->boot(['detector' => ['id' => '']]);
    }

    #[Group('CFG-12')]
    public function testMobileDetectNeedsAtLeastOneHeader(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('device_view.detector.mobile_detect.headers must list at least one header.');

        $this->boot(['detector' => ['mobile_detect' => ['headers' => []]]]);
    }

    public function testAMobileDetectHeaderCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "device_view.detector.mobile_detect.headers.0" cannot contain an empty value');

        $this->boot(['detector' => ['mobile_detect' => ['headers' => ['']]]]);
    }

    #[Group('CFG-13')]
    public function testTheMobileDetectCacheMustBeAPsr16Cache(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.not_a_cache" set in device_view.detector.mobile_detect.cache must implement Psr\SimpleCache\CacheInterface.');

        $this->boot(['detector' => ['mobile_detect' => ['cache' => 'app.not_a_cache']]], ['app.not_a_cache' => \stdClass::class]);
    }

    public function testAMissingMobileDetectCacheFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The service "app.nope" set in device_view.detector.mobile_detect.cache does not exist.');

        $this->boot(['detector' => ['mobile_detect' => ['cache' => 'app.nope']]]);
    }

    public function testTheMobileDetectCacheIsNotCheckedForACustomDetector(): void
    {
        $container = $this->boot(
            ['detector' => ['id' => 'app.header_detector', 'mobile_detect' => ['cache' => 'app.nope']]],
            ['app.header_detector' => HeaderDetector::class],
        );

        self::assertInstanceOf(HeaderDetector::class, $container->get('test.device_view.detector'));
    }

    /**
     * @param array<string, mixed>        $config
     * @param array<string, class-string> $services
     */
    private function boot(array $config, array $services = []): ContainerInterface
    {
        $this->kernel = new Kernel($config, services: $services);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function detector(array $config): DeviceDetectorInterface
    {
        $detector = $this->boot($config)->get('test.device_view.detector');
        self::assertInstanceOf(DeviceDetectorInterface::class, $detector);

        return $detector;
    }

    private static function request(string $fixture): Request
    {
        $request = Request::create('http://localhost/view');
        $request->headers->set('User-Agent', self::userAgent($fixture));

        return $request;
    }

    private static function userAgent(string $fixture): string
    {
        /** @var array<string, array{user_agent: string|null}> $fixtures */
        $fixtures = require \dirname(__DIR__).'/Fixtures/user-agents.php';
        $userAgent = $fixtures[$fixture]['user_agent'] ?? null;
        self::assertIsString($userAgent);

        return $userAgent;
    }
}
