<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Redirect;

use DeviceViewBundle\Redirect\RedirectHost;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RedirectHostTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int|null, string}>
     */
    public static function validUrls(): iterable
    {
        yield 'host' => ['https://m.example.com', 'https', 'm.example.com', null, ''];
        yield 'case' => ['HTTPS://M.Example.COM', 'https', 'm.example.com', null, ''];
        yield 'trailing dot and slash' => ['https://m.example.com./', 'https', 'm.example.com', null, ''];
        yield 'default http port' => ['http://m.example.com:80', 'http', 'm.example.com', null, ''];
        yield 'default https port' => ['https://m.example.com:443', 'https', 'm.example.com', null, ''];
        yield 'https port on http' => ['http://m.example.com:443', 'http', 'm.example.com', 443, ''];
        yield 'other port' => ['http://m.example.com:8080', 'http', 'm.example.com', 8080, ''];
        yield 'base path' => ['https://m.example.com/shop/', 'https', 'm.example.com', null, '/shop'];
        yield 'deep base path' => ['https://m.example.com/a/b//', 'https', 'm.example.com', null, '/a/b'];
        yield 'IPv6' => ['http://[2001:db8::1]:8080', 'http', '[2001:db8::1]', 8080, ''];
    }

    #[DataProvider('validUrls')]
    public function testParseNormalisesTheUrl(string $url, string $scheme, string $host, ?int $port, string $basePath): void
    {
        $parsed = RedirectHost::parse($url);

        self::assertNotNull($parsed);
        self::assertSame($scheme, $parsed->scheme);
        self::assertSame($host, $parsed->host);
        self::assertSame($port, $parsed->port);
        self::assertSame($basePath, $parsed->basePath);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'no scheme' => ['m.example.com'];
        yield 'other scheme' => ['ftp://m.example.com'];
        yield 'no host' => ['https://'];
        yield 'path only' => ['https:/m.example.com'];
        yield 'dot host' => ['https://./'];
        yield 'query' => ['https://m.example.com/?a=1'];
        yield 'empty query' => ['https://m.example.com?'];
        yield 'fragment' => ['https://m.example.com#top'];
        yield 'empty fragment' => ['https://m.example.com#'];
        yield 'user' => ['https://user@m.example.com'];
        yield 'user and password' => ['https://user:pass@m.example.com'];
        yield 'password only' => ['https://:pass@m.example.com'];
        yield 'space' => ['https://m.example.com/a b'];
        yield 'leading space' => [' https://m.example.com'];
        yield 'tab' => ["https://m.example.com/\t"];
        yield 'new line' => ["https://m.example.com/\r\nX: 1"];
        yield 'nul' => ["https://m.example.com/\0"];
        yield 'delete' => ["https://m.example.com/\x7F"];
        yield 'port too large' => ['https://m.example.com:70000'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidUrls')]
    public function testParseRejectsAnythingElse(string $url): void
    {
        self::assertNull(RedirectHost::parse($url));
    }

    public function testAPrintableCharacterIsAccepted(): void
    {
        self::assertSame('/~a!', RedirectHost::parse('https://m.example.com/~a!')?->basePath);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function currentHosts(): iterable
    {
        yield 'same host' => ['https://m.example.com', 'https://m.example.com/view', true];
        yield 'other scheme, default ports' => ['https://m.example.com', 'http://m.example.com/view', true];
        yield 'other host' => ['https://m.example.com', 'https://www.example.com/view', false];
        yield 'request case' => ['https://m.example.com', 'https://M.Example.COM/view', true];
        yield 'request trailing dot' => ['https://m.example.com', 'https://m.example.com./view', true];
        yield 'configured trailing dot' => ['https://m.example.com.', 'https://m.example.com/view', true];
        yield 'request port' => ['https://m.example.com', 'https://m.example.com:8443/view', false];
        yield 'configured port' => ['https://m.example.com:8443', 'https://m.example.com/view', false];
        yield 'same port' => ['https://m.example.com:8443', 'https://m.example.com:8443/view', true];
        yield 'explicit default port' => ['https://m.example.com:443', 'https://m.example.com:443/view', true];
        yield 'http default port' => ['http://m.example.com:80', 'http://m.example.com/view', true];
        yield 'a port that is the default of the other scheme' => ['http://m.example.com:443', 'https://m.example.com/view', false];
        yield 'base path' => ['https://m.example.com/shop', 'https://m.example.com/view', true];
        yield 'subdomain' => ['https://example.com', 'https://m.example.com/', false];
    }

    #[DataProvider('currentHosts')]
    public function testIsCurrentHostComparesTheNormalisedHostAndPort(string $configured, string $uri, bool $expected): void
    {
        self::assertSame($expected, RedirectHost::parse($configured)?->isCurrentHost(Request::create($uri)));
    }

    public function testIsCurrentHostReadsTheHostOfATrustedProxy(): void
    {
        $request = Request::create('http://10.0.0.5/view', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('X-Forwarded-Host', 'm.example.com');
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_HOST);

        try {
            self::assertTrue(RedirectHost::parse('https://m.example.com')?->isCurrentHost($request));
        } finally {
            Request::setTrustedProxies([], 0);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function urls(): iterable
    {
        yield 'host' => ['https://m.example.com', 'm.example.com', 'https://m.example.com/blog/post?a=1'];
        yield 'port' => ['http://m.example.com:8080/', 'm.example.com:8080', 'http://m.example.com:8080/blog/post?a=1'];
        yield 'default port' => ['https://M.example.com.:443', 'm.example.com', 'https://m.example.com/blog/post?a=1'];
        yield 'base path' => ['https://m.example.com/shop/', 'm.example.com', 'https://m.example.com/shop/blog/post?a=1'];
    }

    #[DataProvider('urls')]
    public function testAuthorityAndUrl(string $configured, string $authority, string $url): void
    {
        $host = RedirectHost::parse($configured);

        self::assertNotNull($host);
        self::assertSame($authority, $host->authority());
        self::assertSame($url, $host->url('/blog/post?a=1'));
    }
}
