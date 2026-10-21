<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use DeviceViewBundle\Context\DeviceViewContext;
use DeviceViewBundle\Tests\App\TestController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\FragmentUriGenerator;

/**
 * Skipped requests (skip), through the kernel: the fragment path and the paths of skip.paths
 * still resolve their view when asked, but never switch, redirect, remember or expire a stored
 * value.
 */
final class SkipTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    private const array M = ['mobile' => ['enabled' => true, 'host' => 'https://m.example.com']];

    private const array SKIP_01 = ['redirect' => self::M, 'remember' => ['detected_view' => true]];

    private const array API = ['skip' => ['paths' => ['^/api']]];

    #[Group('SKIP-01')]
    #[Group('REG-13')]
    public function testTheFragmentPathIsSkipped(): void
    {
        $this->boot(self::SKIP_01, framework: ['fragments' => true]);
        $request = self::request($this->signedFragmentUrl(), 'iphone', cookies: ['device_view' => 'phone']);

        $response = $this->handle($request);

        self::assertPage('mobile detector', $response);
        self::assertTrue(self::context($request)->skipped);
    }

    #[Group('SKIP-02')]
    public function testFragmentsAreNotSkippedWhenSkipFragmentsIsFalse(): void
    {
        $this->boot(self::SKIP_01 + ['skip' => ['fragments' => false]], framework: ['fragments' => true]);
        $url = $this->signedFragmentUrl();
        $request = self::request($url, 'iphone', cookies: ['device_view' => 'phone']);

        $response = $this->handle($request);

        self::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('https://m.example.com/_fragment?'.self::queryWithoutPath($url).'&device_view=mobile', $response->headers->get('Location'));
        self::assertFalse(self::context($request)->skipped);
    }

    #[Group('SKIP-03')]
    public function testACustomFragmentPathIsSkipped(): void
    {
        $this->boot(self::SKIP_01, framework: ['fragments' => ['path' => '/_frag']]);
        $url = $this->signedFragmentUrl();
        self::assertStringStartsWith('http://www.example.com/_frag?', $url);
        $request = self::request($url, 'iphone', cookies: ['device_view' => 'phone']);

        $response = $this->handle($request);

        self::assertPage('mobile detector', $response);
        self::assertTrue(self::context($request)->skipped);
    }

    #[Group('SKIP-03')]
    public function testTheDefaultFragmentPathIsNotSkippedWhenFragmentsUseAnotherPath(): void
    {
        $this->boot(self::SKIP_01, framework: ['fragments' => ['path' => '/_frag']]);

        $response = $this->handle(self::request('http://www.example.com/_fragment', 'iphone'));

        self::assertSame('https://m.example.com/_fragment?device_view=mobile', $response->headers->get('Location'));
    }

    #[Group('SKIP-04')]
    public function testTheDefaultFragmentPathIsSkippedWhenFragmentsAreOff(): void
    {
        $this->boot(self::SKIP_01);
        $request = self::request('http://www.example.com/_fragment', 'iphone');

        $response = $this->handle($request);

        self::assertPage('mobile detector', $response);
        self::assertTrue(self::context($request)->skipped);
    }

    #[Group('SKIP-05')]
    public function testProfilerPathsAreSkippedByDefault(): void
    {
        $this->boot(['redirect' => self::M]);

        foreach (['/_profiler/abc', '/_wdt/abc'] as $path) {
            $response = $this->handle(self::request('http://www.example.com'.$path, 'iphone'));

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertSame('mobile detector', $response->getContent(), $path);
            self::assertFalse($response->headers->has('Location'), $path);
        }
    }

    #[Group('SKIP-06')]
    public function testCustomSkipPaths(): void
    {
        $this->boot(self::API);
        $request = self::request('/api/thing?device_view=mobile');

        $response = $this->handle($request);

        self::assertPage('desktop detector', $response);
        self::assertTrue(self::context($request)->skipped);
    }

    #[Group('SKIP-07')]
    public function testCustomPathsReplaceTheDefaults(): void
    {
        $this->boot(['redirect' => self::M] + self::API);

        $response = $this->handle(self::request('http://www.example.com/_profiler/abc', 'iphone'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://m.example.com/_profiler/abc?device_view=mobile', $response->headers->get('Location'));
    }

    #[Group('SKIP-08')]
    public function testASkippedRequestStillResolvesAndVaries(): void
    {
        $this->boot(self::API);

        $response = $this->handle(self::request('/api/thing', 'iphone'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('mobile detector', $response->getContent());
        self::assertSame(['Cookie', 'User-Agent'], $response->getVary());
    }

    #[Group('SKIP-09')]
    public function testPathsMatchThePathInfo(): void
    {
        $this->boot(self::API);
        $request = Request::create('http://localhost/app/api/thing?device_view=mobile', server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/srv/app/index.php']);
        $request->headers->set('User-Agent', (string) self::userAgent('desktop_chrome'));

        $response = $this->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('desktop detector', $response->getContent());
        self::assertFalse($response->headers->has('Location'));
        self::assertTrue(self::context($request)->skipped);
    }

    public function testAPathOutsideTheSkipPathsIsNotSkipped(): void
    {
        $this->boot(self::API);
        $request = self::request('/view?device_view=mobile');

        $response = $this->handle($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertFalse(self::context($request)->skipped);
    }

    public function testASkippedRequestKeepsAViewForcedToBeRemembered(): void
    {
        $this->boot(['skip' => ['paths' => ['^/set-view/']]]);

        $response = $this->handle(self::request('/set-view/tablet?remember=1', 'iphone'));

        self::assertPage('tablet forced', $response);
    }

    /**
     * A signed URL of the fragment path rendering the view controller, on www.example.com, as an
     * ESI or hinclude tag gives it.
     */
    private function signedFragmentUrl(): string
    {
        \assert(null !== $this->kernel);
        $container = $this->kernel->getContainer();
        $testContainer = $container->get('test.service_container');
        \assert($testContainer instanceof ContainerInterface);
        $signer = $testContainer->get('uri_signer');
        \assert($signer instanceof UriSigner);
        $fragmentPath = $container->getParameter('fragment.path');
        \assert(\is_string($fragmentPath));

        return new FragmentUriGenerator($fragmentPath, $signer)->generate(new ControllerReference(TestController::class.'::view'), Request::create('http://www.example.com/'), true);
    }

    /**
     * The query of the URL rebuilt as the redirects do it (RED-25), without the _path parameter
     * that the FragmentListener removes from the request before the redirect listener runs.
     */
    private static function queryWithoutPath(string $url): string
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        unset($query['_path']);

        return http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }

    private static function context(Request $request): DeviceViewContext
    {
        $context = $request->attributes->get(DeviceViewContext::ATTRIBUTE);
        self::assertInstanceOf(DeviceViewContext::class, $context);

        return $context;
    }

    private static function assertPage(string $body, Response $response): void
    {
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($body, $response->getContent());
        self::assertFalse($response->headers->has('Location'));
        self::assertNoCookie($response);
    }
}
