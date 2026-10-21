<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Kernel;
use DeviceViewBundle\Tests\App\TrustedProxies;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testADebugKernelLeavesShellVerbosityAsItFoundIt(): void
    {
        $before = [getenv('SHELL_VERBOSITY'), $_ENV['SHELL_VERBOSITY'] ?? null, $_SERVER['SHELL_VERBOSITY'] ?? null];
        $this->kernel = new Kernel(debug: true);
        $this->kernel->boot();

        $this->kernel->shutdown();

        self::assertSame($before, [getenv('SHELL_VERBOSITY'), $_ENV['SHELL_VERBOSITY'] ?? null, $_SERVER['SHELL_VERBOSITY'] ?? null]);
    }

    /**
     * @return iterable<string, array{array{proxies: array<string>, headers: int}}>
     */
    public static function trustedProxies(): iterable
    {
        yield 'the initial state of Request' => [['proxies' => [], 'headers' => -1]];
        yield 'no header' => [['proxies' => [], 'headers' => 0]];
        yield 'a proxy and a header' => [['proxies' => ['198.51.100.7'], 'headers' => Request::HEADER_X_FORWARDED_FOR]];
    }

    /**
     * @param array{proxies: array<string>, headers: int} $before
     */
    #[DataProvider('trustedProxies')]
    public function testAKernelLeavesTheTrustedProxiesAsItFoundThem(array $before): void
    {
        $outside = TrustedProxies::save();
        TrustedProxies::restore($before);

        try {
            $this->kernel = new Kernel(framework: ['trusted_proxies' => '192.0.2.1', 'trusted_headers' => ['x-forwarded-host']]);
            $this->kernel->boot();
            self::assertSame([['192.0.2.1'], Request::HEADER_X_FORWARDED_HOST], [Request::getTrustedProxies(), Request::getTrustedHeaderSet()]);

            $this->kernel->shutdown();

            self::assertSame($before, TrustedProxies::save());
        } finally {
            TrustedProxies::restore($outside);
        }
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
