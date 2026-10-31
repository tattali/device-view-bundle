<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\HttpFoundation\Request;

/**
 * The bundle next to WebProfilerBundle, with the profiler on. The profiler needs TwigBundle, which comes with it.
 */
final class WithWebProfilerTest extends TestCase
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testTheKernelBootsWithTheWebProfiler(): void
    {
        $this->kernel = self::kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertArrayHasKey('WebProfilerBundle', $this->kernel->getBundles());
        self::assertArrayHasKey('DeviceViewBundle', $this->kernel->getBundles());
        self::assertArrayHasKey('WebProfilerBundle', (array) $container->getParameter('kernel.bundles'));
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

    public function testProfilerPathsStayOutOfTheViewSwitch(): void
    {
        $this->kernel = self::kernel(['redirect' => ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']]]);
        $request = Request::create('http://www.example.com/_profiler/abc');
        $request->headers->set('User-Agent', (string) self::iphone());

        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($response->headers->get('Location'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function kernel(array $config = []): Kernel
    {
        return new Kernel(
            $config,
            framework: ['profiler' => ['collect' => false]],
            extraBundles: [new TwigBundle(), new WebProfilerBundle()],
        );
    }

    private static function iphone(): ?string
    {
        /** @var array<string, array{user_agent: string|null}> $fixtures */
        $fixtures = require \dirname(__DIR__).'/Fixtures/user-agents.php';

        return $fixtures['iphone']['user_agent'];
    }
}
