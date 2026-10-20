<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Tests\App\Kernel;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One booted kernel serving many requests in a row, as in a FrankenPHP or RoadRunner worker:
 * no view, source or forced view may leak from one request to the next.
 */
final class WorkerTest extends TestCase
{
    use HandlesRequests;

    private const int REQUESTS = 200;

    private const array PATHS = [
        '/view',
        '/',
        '/readers/mobile',
        '/readers/tablet',
        '/set-view/mobile',
        '/set-view/tablet',
        '/set-view/desktop',
        '/with-sub-request',
    ];

    private const array CONFIG = ['tablet_as_mobile' => false, 'default_view' => 'tablet'];

    public function testOneKernelAnswersLikeAFreshKernel(): void
    {
        $this->boot(self::CONFIG);

        foreach (self::requests() as $index => [$path, $userAgent]) {
            $response = $this->handle(self::request($path, $userAgent));

            self::assertSame(self::fresh($path, $userAgent), self::summary($response), \sprintf('request %d: %s with %s', $index, $path, $userAgent));
        }
    }

    public function testAForcedViewDoesNotLeakToTheNextRequest(): void
    {
        $this->boot(self::CONFIG);

        self::assertSame('desktop forced', $this->handle(self::request('/set-view/desktop', 'iphone'))->getContent());
        self::assertSame('mobile detector', $this->handle(self::request('/view', 'iphone'))->getContent());
        self::assertSame('tablet default', $this->handle(self::request('/view', 'empty'))->getContent());
    }

    public function testServerUserAgentChangesNothing(): void
    {
        $this->boot(self::CONFIG);
        $before = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            unset($_SERVER['HTTP_USER_AGENT']);
            $withoutServer = $this->summaries();
            $_SERVER['HTTP_USER_AGENT'] = self::userAgent('iphone');
            $withServer = $this->summaries();
        } finally {
            if (null === $before) {
                unset($_SERVER['HTTP_USER_AGENT']);
            } else {
                $_SERVER['HTTP_USER_AGENT'] = $before;
            }
        }

        self::assertSame($withoutServer, $withServer);
    }

    public function testMemoryStaysFlat(): void
    {
        $this->boot(self::CONFIG);
        $after50 = 0;

        foreach (self::requests() as $index => [$path, $userAgent]) {
            $this->handle(self::request($path, $userAgent));
            if (49 === $index) {
                gc_collect_cycles();
                $after50 = memory_get_usage();
            }
        }
        gc_collect_cycles();

        self::assertLessThan(1024 * 1024, memory_get_usage() - $after50);
    }

    /**
     * @return list<array{string, string}> the path and user agent fixture of each request, from a fixed seed
     */
    private static function requests(): array
    {
        $randomizer = new Randomizer(new Mt19937(20261020));
        $userAgents = array_keys(self::userAgentFixtures());
        $requests = [];
        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $requests[] = [self::PATHS[$randomizer->getInt(0, \count(self::PATHS) - 1)], $userAgents[$randomizer->getInt(0, \count($userAgents) - 1)]];
        }

        return $requests;
    }

    /**
     * @return list<array{int, string, list<string>, string|null}>
     */
    private function summaries(): array
    {
        $summaries = [];
        foreach (self::requests() as [$path, $userAgent]) {
            $summaries[] = self::summary($this->handle(self::request($path, $userAgent)));
        }

        return $summaries;
    }

    /**
     * @return array{int, string, list<string>, string|null}
     */
    private static function fresh(string $path, string $userAgent): array
    {
        $kernel = new Kernel(self::CONFIG);
        $request = self::request($path, $userAgent);

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return self::summary($response);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @return array{int, string, list<string>, string|null}
     */
    private static function summary(Response $response): array
    {
        return [
            $response->getStatusCode(),
            (string) $response->getContent(),
            array_values(array_map(strval(...), $response->headers->getCookies())),
            $response->headers->get('Vary'),
        ];
    }
}
