<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use DeviceViewBundle\Tests\App\Detector\HeaderDetector;
use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The container build without the detector libraries.
 *
 * Both libraries are dev dependencies, so each test runs in its own process and hides them
 * from the autoloader before anything loads them.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MissingDetectorLibraryTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function setUp(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('Detection\\', []);
            $loader->setPsr4('DeviceDetector\\', []);
        }

        self::assertFalse(class_exists('Detection\MobileDetect'), 'Mobile Detect is hidden');
        self::assertFalse(class_exists('DeviceDetector\DeviceDetector'), 'Matomo Device Detector is hidden');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    #[Group('DET-AUTO-03')]
    #[Group('CFG-46')]
    public function testAutoWithoutAnyLibraryFails(): void
    {
        $this->kernel = new Kernel(['detector' => ['id' => 'auto']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No device detector is installed. Run `composer require mobiledetect/mobiledetectlib` (or `matomo/device-detector`), or set `device_view.detector.id` to `client_hints` or to your own service id.');

        $this->kernel->boot();
    }

    public function testTheDefaultsWithoutAnyLibraryFail(): void
    {
        $this->kernel = new Kernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No device detector is installed.');

        $this->kernel->boot();
    }

    #[Group('CFG-47')]
    public function testMobileDetectWithoutItsLibraryFails(): void
    {
        $this->kernel = new Kernel(['detector' => ['id' => 'mobile_detect']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "mobile_detect" detector needs mobiledetect/mobiledetectlib. Run "composer require mobiledetect/mobiledetectlib", or set device_view.detector.id to another detector.');

        $this->kernel->boot();
    }

    public function testACustomDetectorNeedsNoLibrary(): void
    {
        $this->kernel = new Kernel(['detector' => ['id' => 'app.header_detector']], services: ['app.header_detector' => HeaderDetector::class]);
        $this->kernel->boot();

        self::assertInstanceOf(HeaderDetector::class, $this->kernel->getContainer()->get('test.device_view.detector'));
    }
}
