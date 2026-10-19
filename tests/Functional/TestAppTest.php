<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;

final class TestAppTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testTheAppWithTheBundleHandlesARequest(): void
    {
        $this->kernel = new Kernel();
        $request = Request::create('/');

        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('home page', $response->getContent());
        self::assertArrayHasKey('DeviceViewBundle', $this->kernel->getBundles());
    }

    public function testTheAppWritesNoFileInTheRepository(): void
    {
        $this->kernel = new Kernel(debug: true);
        $this->kernel->boot();

        self::assertFileDoesNotExist(\dirname(__DIR__).'/App/config/reference.php');
    }

    #[Group('CFG-01')]
    public function testTheConfigurationRootIsDeviceView(): void
    {
        $this->kernel = new Kernel(['unknown_key' => true]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "unknown_key" under "device_view".');

        $this->kernel->boot();
    }
}
