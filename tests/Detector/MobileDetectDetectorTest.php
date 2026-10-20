<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Detector;

use DeviceViewBundle\Detector\MobileDetectDetector;
use DeviceViewBundle\Tests\App\DetectorCache;
use DeviceViewBundle\View\DeviceType;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MobileDetectDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, DeviceType|null}>
     */
    public static function userAgents(): iterable
    {
        foreach (self::fixtures() as $name => $fixture) {
            yield $name => [$fixture['user_agent'], DeviceType::tryFrom($fixture['expected']['mobile_detect'] ?? '')];
        }
    }

    #[DataProvider('userAgents')]
    public function testEveryUserAgentFixtureGetsItsExpectedDeviceType(?string $userAgent, ?DeviceType $expected): void
    {
        self::assertSame($expected, new MobileDetectDetector()->detect(self::request($userAgent)));
    }

    public function testItVariesOnTheUserAgentByDefault(): void
    {
        self::assertSame(['User-Agent'], new MobileDetectDetector()->varyHeaders());
    }

    public function testTheConfiguredHeadersArePassedAndListedForVary(): void
    {
        $detector = new MobileDetectDetector(['User-Agent', 'X-Device-User-Agent']);
        $request = self::request(self::userAgent('desktop_chrome'));
        $request->headers->set('X-Device-User-Agent', self::userAgent('iphone'));

        self::assertSame(DeviceType::Mobile, $detector->detect($request));
        self::assertSame(['User-Agent', 'X-Device-User-Agent'], $detector->varyHeaders());
    }

    public function testAHeaderOtherThanTheUserAgentCanBeTheOnlyOne(): void
    {
        $detector = new MobileDetectDetector(['X-Device-User-Agent']);
        $request = self::request(self::userAgent('desktop_chrome'));
        $request->headers->set('X-Device-User-Agent', self::userAgent('ipad_legacy'));

        self::assertSame(DeviceType::Tablet, $detector->detect($request));
        self::assertNull($detector->detect(self::request(self::userAgent('iphone'))), 'the User-Agent header is not configured');
    }

    public function testHeadersThatAreNotConfiguredAreNotPassed(): void
    {
        $request = self::request(self::userAgent('desktop_chrome'));
        $request->headers->set('X-Device-User-Agent', self::userAgent('iphone'));

        self::assertSame(DeviceType::Desktop, new MobileDetectDetector()->detect($request));
    }

    public function testAUserAgentOfSpacesIsEmpty(): void
    {
        self::assertNull(new MobileDetectDetector()->detect(self::request('   ')));
    }

    #[BackupGlobals(true)]
    public function testItReadsTheRequestAndNeverServer(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = self::userAgent('iphone');
        $detector = new MobileDetectDetector();

        self::assertSame(DeviceType::Desktop, $detector->detect(self::request(self::userAgent('desktop_chrome'))));
        self::assertNull($detector->detect(self::request(null)), 'no User-Agent header: $_SERVER is not a fallback');
    }

    public function testEachCallStartsFromAFreshMobileDetect(): void
    {
        $detector = new MobileDetectDetector(['User-Agent', 'X-Device-User-Agent']);
        $withDeviceHeader = self::request(self::userAgent('desktop_chrome'));
        $withDeviceHeader->headers->set('X-Device-User-Agent', self::userAgent('iphone'));

        self::assertSame(DeviceType::Mobile, $detector->detect($withDeviceHeader));
        self::assertSame(DeviceType::Desktop, $detector->detect(self::request(self::userAgent('desktop_chrome'))));
        self::assertSame(DeviceType::Tablet, $detector->detect(self::request(self::userAgent('ipad_legacy'))));
        self::assertNull($detector->detect(self::request(null)));
        self::assertSame(DeviceType::Mobile, $detector->detect(self::request(self::userAgent('iphone'))));
    }

    public function testTheDetectorHoldsNoMobileDetectAndNoRequest(): void
    {
        $properties = new \ReflectionClass(MobileDetectDetector::class)->getProperties();

        foreach ($properties as $property) {
            self::assertTrue($property->isReadOnly(), $property->getName().' is read-only');
        }
        self::assertSame(['headers', 'cache'], array_map(static fn (\ReflectionProperty $property): string => $property->getName(), $properties));
    }

    public function testMemoryStaysFlatAcrossManyUserAgents(): void
    {
        $detector = new MobileDetectDetector();
        $requests = array_map(
            static fn (array $fixture): Request => self::request($fixture['user_agent']),
            array_values(self::fixtures()),
        );

        $usageAfter = [];
        for ($round = 1; $round <= 200; ++$round) {
            foreach ($requests as $request) {
                $detector->detect($request);
            }
            if (50 === $round || 200 === $round) {
                gc_collect_cycles();
                $usageAfter[$round] = memory_get_usage();
            }
        }

        self::assertLessThan(1024 * 1024, $usageAfter[200] - $usageAfter[50]);
    }

    public function testTheCacheIsUsed(): void
    {
        $cache = new DetectorCache();
        $detector = new MobileDetectDetector(['User-Agent'], $cache);

        self::assertSame(DeviceType::Mobile, $detector->detect(self::request(self::userAgent('iphone'))));
        self::assertNotSame([], $cache->pool->getValues());
        self::assertSame(DeviceType::Mobile, $detector->detect(self::request(self::userAgent('iphone'))));
    }

    private static function request(?string $userAgent): Request
    {
        $request = Request::create('http://localhost/view');
        $request->headers->remove('User-Agent');
        if (null !== $userAgent) {
            $request->headers->set('User-Agent', $userAgent);
        }

        return $request;
    }

    private static function userAgent(string $name): string
    {
        $userAgent = self::fixtures()[$name]['user_agent'] ?? null;
        self::assertIsString($userAgent);

        return $userAgent;
    }

    /**
     * @return array<string, array{user_agent: string|null, expected: array<string, string|null>}>
     */
    private static function fixtures(): array
    {
        /** @var array<string, array{user_agent: string|null, expected: array<string, string|null>}> $fixtures */
        $fixtures = require \dirname(__DIR__).'/Fixtures/user-agents.php';

        return $fixtures;
    }
}
