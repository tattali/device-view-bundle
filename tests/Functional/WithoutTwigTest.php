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
 * The bundle without Twig and TwigBundle, which are dev dependencies only.
 *
 * Each test runs in its own process and hides the two packages from the autoloader before
 * anything loads them.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WithoutTwigTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function setUp(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('Twig\\', []);
            $loader->setPsr4('Symfony\Bundle\TwigBundle\\', []);
        }

        self::assertFalse(class_exists(\Twig\Environment::class), 'Twig is hidden');
        self::assertFalse(class_exists(\Symfony\Bundle\TwigBundle\TwigBundle::class), 'TwigBundle is hidden');
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

    public function testTheContainerHoldsNoTwigService(): void
    {
        $this->kernel = new Kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertFalse($container->hasParameter('twig.default_path'));
        self::assertArrayNotHasKey('TwigBundle', $this->kernel->getBundles());
    }
}
