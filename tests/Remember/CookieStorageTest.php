<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Remember;

use DeviceViewBundle\Remember\CookieStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CookieStorageTest extends TestCase
{
    public function testItReadsTheCookieAsTheRequestGivesIt(): void
    {
        $storage = new CookieStorage();

        self::assertNull($storage->read(Request::create('/')));
        self::assertSame('Tablet ', $storage->read(Request::create('/', cookies: ['device_view' => 'Tablet '])));
        self::assertSame('', $storage->read(Request::create('/', cookies: ['device_view' => ''])));
        self::assertSame(['a' => 'mobile'], $storage->read(Request::create('/', cookies: ['device_view' => ['a' => 'mobile']])));
    }

    public function testItReadsItsOwnName(): void
    {
        $storage = new CookieStorage('dv');

        self::assertSame('tablet', $storage->read(Request::create('/', cookies: ['dv' => 'tablet', 'device_view' => 'mobile'])));
        self::assertNull($storage->read(Request::create('/', cookies: ['device_view' => 'mobile'])));
    }

    public function testItVariesOnTheCookieHeader(): void
    {
        self::assertSame(['Cookie'], new CookieStorage()->varyHeaders());
    }

    public function testABrowserSessionCookieByDefault(): void
    {
        $cookie = self::written(new CookieStorage(), Request::create('http://localhost/'), 'mobile', 0);

        self::assertSame('device_view', $cookie->getName());
        self::assertSame('mobile', $cookie->getValue());
        self::assertSame(0, $cookie->getExpiresTime());
        self::assertSame('/', $cookie->getPath());
        self::assertNull($cookie->getDomain());
        self::assertFalse($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertFalse($cookie->isRaw());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertFalse($cookie->isPartitioned());
    }

    public function testALifetimeSetsTheExpiry(): void
    {
        $before = time();
        $cookie = self::written(new CookieStorage(), Request::create('/'), 'tablet', 3600);

        self::assertGreaterThanOrEqual($before + 3600, $cookie->getExpiresTime());
        self::assertLessThanOrEqual(time() + 3600, $cookie->getExpiresTime());
    }

    public function testTheConfiguredAttributes(): void
    {
        $storage = new CookieStorage('dv', '/shop', '.example.com', true, false, Cookie::SAMESITE_STRICT, true);

        $cookie = self::written($storage, Request::create('http://localhost/'), 'm', 0);

        self::assertSame('dv', $cookie->getName());
        self::assertSame('m', $cookie->getValue());
        self::assertSame('/shop', $cookie->getPath());
        self::assertSame('.example.com', $cookie->getDomain());
        self::assertTrue($cookie->isSecure());
        self::assertFalse($cookie->isHttpOnly());
        self::assertTrue($cookie->isRaw());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testSecureAutoFollowsTheRequest(): void
    {
        self::assertTrue(self::written(new CookieStorage(), Request::create('https://localhost/'), 'mobile', 0)->isSecure());
        self::assertFalse(self::written(new CookieStorage(), Request::create('http://localhost/'), 'mobile', 0)->isSecure());
    }

    public function testSecureFalseOverHttps(): void
    {
        self::assertFalse(self::written(new CookieStorage(secure: false), Request::create('https://localhost/'), 'mobile', 0)->isSecure());
    }

    public function testSameSiteNoneForcesSecure(): void
    {
        $cookie = self::written(new CookieStorage(sameSite: Cookie::SAMESITE_NONE), Request::create('http://localhost/'), 'mobile', 0);

        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_NONE, $cookie->getSameSite());
    }

    public function testSameSiteNull(): void
    {
        self::assertNull(self::written(new CookieStorage(sameSite: null), Request::create('/'), 'mobile', 0)->getSameSite());
    }

    public function testClearExpiresTheCookieWithTheSameAttributes(): void
    {
        $storage = new CookieStorage('dv', '/shop', '.example.com', null, false, Cookie::SAMESITE_STRICT, true);
        $response = new Response();

        self::assertTrue($storage->clear(Request::create('https://localhost/'), $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $cookie = $cookies[0];
        self::assertSame('dv', $cookie->getName());
        self::assertNull($cookie->getValue());
        self::assertTrue($cookie->isCleared());
        self::assertSame('/shop', $cookie->getPath());
        self::assertSame('.example.com', $cookie->getDomain());
        self::assertTrue($cookie->isSecure());
        self::assertFalse($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testClearExpiresAStringCookie(): void
    {
        $response = new Response();

        self::assertTrue(new CookieStorage()->clear(Request::create('/', cookies: ['device_view' => 'phone']), $response));

        self::assertTrue($response->headers->getCookies()[0]->isCleared());
    }

    public function testClearCannotRemoveAnArrayCookie(): void
    {
        // a device_view deletion would not remove the browser's device_view[a] cookie
        $response = new Response();

        self::assertFalse(new CookieStorage()->clear(Request::create('/', cookies: ['device_view' => ['a' => 'mobile']]), $response));

        self::assertSame([], $response->headers->getCookies());
    }

    public function testClearOverHttpWithTheDefaults(): void
    {
        $response = new Response();

        new CookieStorage()->clear(Request::create('http://localhost/'), $response);

        $cookie = $response->headers->getCookies()[0];
        self::assertSame('device_view', $cookie->getName());
        self::assertTrue($cookie->isCleared());
        self::assertSame('/', $cookie->getPath());
        self::assertNull($cookie->getDomain());
        self::assertFalse($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testClearWithSameSiteNoneIsSecure(): void
    {
        $response = new Response();

        new CookieStorage(sameSite: Cookie::SAMESITE_NONE)->clear(Request::create('http://localhost/'), $response);

        self::assertTrue($response->headers->getCookies()[0]->isSecure());
    }

    private static function written(CookieStorage $storage, Request $request, string $name, int $lifetime): Cookie
    {
        $response = new Response();
        $storage->write($request, $response, $name, $lifetime);
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        return $cookies[0];
    }
}
