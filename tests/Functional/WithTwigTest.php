<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpFoundation\Request;

/**
 * The bundle next to TwigBundle.
 */
final class WithTwigTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testTheKernelBootsWithTwig(): void
    {
        $this->kernel = self::kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertArrayHasKey('TwigBundle', $this->kernel->getBundles());
        self::assertArrayHasKey('DeviceViewBundle', $this->kernel->getBundles());
        self::assertTrue($container->hasParameter('twig.default_path'));
    }

    public function testTheBundleStillServesARequest(): void
    {
        $this->kernel = self::kernel();
        $request = Request::create('/');

        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('home page', $response->getContent());
    }

    private static function kernel(): Kernel
    {
        return new Kernel(extraBundles: [new TwigBundle()]);
    }
}
