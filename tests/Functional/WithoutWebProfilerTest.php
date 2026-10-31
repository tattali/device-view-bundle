<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The bundle without WebProfilerBundle, which is a dev dependency only.
 *
 * Each test runs in its own process and hides the package from the autoloader before
 * anything loads it.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WithoutWebProfilerTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function setUp(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('Symfony\Bundle\WebProfilerBundle\\', []);
        }

        self::assertFalse(class_exists(\Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class), 'WebProfilerBundle is hidden');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testTheKernelBootsAndServesARequest(): void
    {
        $this->kernel = new Kernel();
        $request = Request::create('/');

        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('home page', $response->getContent());
    }

    public function testTheContainerHoldsNoProfilerService(): void
    {
        $this->kernel = new Kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertArrayNotHasKey('WebProfilerBundle', (array) $container->getParameter('kernel.bundles'));
        self::assertArrayNotHasKey('WebProfilerBundle', $this->kernel->getBundles());
    }
}
