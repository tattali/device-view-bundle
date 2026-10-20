<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Boots the test application and sends it requests, as docs/scenarios.md describes them.
 */
trait HandlesRequests
{
    private ?Kernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @param array<string, mixed>                       $config     the device_view configuration
     * @param array<string, class-string>                $services   public services of the application, by id
     * @param array<string, mixed>                       $framework  framework configuration added to the baseline
     * @param array<string, array{class-string, string}> $decorators public services decorating a service, by id: [class, decorated id]
     */
    private function boot(array $config = [], array $services = [], array $framework = [], array $decorators = []): ContainerInterface
    {
        $this->kernel?->shutdown();
        $this->kernel = new Kernel($config, services: $services, framework: $framework, decorators: $decorators);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }

    /**
     * Handles the request as the main request of the booted kernel (booting the baseline when
     * none is booted). With $catch false, an exception reaches the test.
     */
    private function handle(Request $request, bool $catch = true): Response
    {
        if (null === $this->kernel) {
            $this->boot();
        }
        \assert(null !== $this->kernel);

        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, $catch);
        $this->kernel->terminate($request, $response);

        return $response;
    }

    /**
     * GET http://localhost<path> with the user agent of a fixture of tests/Fixtures/user-agents.php
     * (the header is removed for the "missing" fixture). A full URL is used as given.
     *
     * @param array<string, mixed> $cookies the request cookies, as PHP decodes them
     */
    private static function request(string $path, string $userAgent = 'desktop_chrome', string $method = 'GET', array $cookies = []): Request
    {
        $request = Request::create(str_contains($path, '://') ? $path : 'http://localhost'.$path, $method, cookies: $cookies);
        $value = self::userAgent($userAgent);
        if (null === $value) {
            $request->headers->remove('User-Agent');
        } else {
            $request->headers->set('User-Agent', $value);
        }

        return $request;
    }

    private static function userAgent(string $fixture): ?string
    {
        $fixtures = self::userAgentFixtures();
        if (!\array_key_exists($fixture, $fixtures)) {
            throw new \InvalidArgumentException(\sprintf('Unknown user agent fixture "%s".', $fixture));
        }

        return $fixtures[$fixture]['user_agent'];
    }

    /**
     * @return array<string, array{user_agent: string|null, expected: array{mobile_detect: string|null}}>
     */
    private static function userAgentFixtures(): array
    {
        /** @var array<string, array{user_agent: string|null, expected: array{mobile_detect: string|null}}> $fixtures */
        $fixtures = require \dirname(__DIR__).'/Fixtures/user-agents.php';

        return $fixtures;
    }

    private function service(string $id): object
    {
        \assert(null !== $this->kernel);

        return $this->kernel->getContainer()->get($id);
    }
}
