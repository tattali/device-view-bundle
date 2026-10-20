<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Set-Cookie shorthands of docs/scenarios.md ("Expected results") and their assertion.
 *
 * An expected cookie is written with the E(n) placeholder of the catalogue: the expiry date
 * n seconds from now. The assertion allows one second of clock drift, on the expiry date and on
 * Max-Age.
 */
trait AssertsCookies
{
    /**
     * SC(v) and SC(v, n): a browser-session cookie, or one that lasts n seconds.
     */
    private static function sc(string $view, ?int $lifetime = null): string
    {
        return self::cookieString($view, $lifetime, false);
    }

    /**
     * SCS(v) and SCS(v, n): as SC, with the secure attribute.
     */
    private static function scs(string $view, ?int $lifetime = null): string
    {
        return self::cookieString($view, $lifetime, true);
    }

    /**
     * SC-DEL: the expired device_view cookie.
     */
    private static function scDel(): string
    {
        return 'device_view=deleted; expires=E(-31536001); Max-Age=0; path=/; httponly; samesite=lax';
    }

    /**
     * Asserts that the response sets exactly one cookie, whose raw value is the expected one.
     */
    private static function assertSetCookie(string $expected, Response $response): void
    {
        $cookies = $response->headers->getCookies();
        Assert::assertCount(1, $cookies, 'the response sets one cookie');
        $actual = (string) $cookies[0];

        $candidates = [];
        foreach ([0, 1] as $expiresDrift) {
            foreach ([0, 1] as $maxAgeDrift) {
                $candidates[] = self::expand($expected, $expiresDrift, $maxAgeDrift);
            }
        }

        Assert::assertContains($actual, $candidates, \sprintf('Set-Cookie: %s', $actual));
    }

    private static function assertNoCookie(Response $response): void
    {
        Assert::assertSame([], $response->headers->getCookies(), 'the response sets no cookie');
    }

    private static function cookieString(string $view, ?int $lifetime, bool $secure): string
    {
        $expiry = null === $lifetime ? '' : \sprintf('; expires=E(%d); Max-Age=%d', $lifetime, $lifetime);

        return \sprintf('device_view=%s%s; path=/%s; httponly; samesite=lax', $view, $expiry, $secure ? '; secure' : '');
    }

    /**
     * Replaces E(n) with the date n seconds before $expiresDrift seconds ago, and a positive
     * Max-Age with $maxAgeDrift seconds less.
     */
    private static function expand(string $expected, int $expiresDrift, int $maxAgeDrift): string
    {
        $now = time();
        $expected = (string) preg_replace_callback(
            '/E\((-?\d+)\)/',
            static fn (array $match): string => gmdate('D, d M Y H:i:s T', $now - $expiresDrift + (int) $match[1]),
            $expected,
        );

        return (string) preg_replace_callback(
            '/Max-Age=(\d+)/',
            static fn (array $match): string => 'Max-Age='.max(0, (int) $match[1] - $maxAgeDrift),
            $expected,
        );
    }
}
