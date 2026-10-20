<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The attributes of the remember cookie (device_view.remember.cookie), through the kernel. Each
 * test switches with GET /view?device_view=mobile unless it says otherwise.
 */
final class CookieTest extends TestCase
{
    use AssertsCookies;
    use HandlesRequests;

    #[Group('CK-01')]
    public function testDefaultsOverHttp(): void
    {
        self::assertSetCookie('device_view=mobile; path=/; httponly; samesite=lax', $this->switch('http://localhost/view?device_view=mobile'));
    }

    #[Group('CK-02')]
    public function testSecureAutoOverHttps(): void
    {
        self::assertSetCookie('device_view=mobile; path=/; secure; httponly; samesite=lax', $this->switch('https://localhost/view?device_view=mobile'));
    }

    #[Group('CK-03')]
    public function testSecureTrueOverHttp(): void
    {
        $this->boot(['remember' => ['cookie' => ['secure' => true]]]);

        self::assertSetCookie('device_view=mobile; path=/; secure; httponly; samesite=lax', $this->switch('http://localhost/view?device_view=mobile'));
    }

    #[Group('CK-04')]
    public function testSecureFalseOverHttps(): void
    {
        $this->boot(['remember' => ['cookie' => ['secure' => false]]]);

        self::assertSetCookie('device_view=mobile; path=/; httponly; samesite=lax', $this->switch('https://localhost/view?device_view=mobile'));
    }

    #[Group('CK-05')]
    public function testCustomNameIsWritten(): void
    {
        $this->boot(['remember' => ['cookie' => ['name' => 'dv']]]);

        self::assertSetCookie('dv=mobile; path=/; httponly; samesite=lax', $this->switch());
    }

    #[Group('CK-06')]
    public function testCustomNameIsReadTheDefaultNameIsNot(): void
    {
        $this->boot(['remember' => ['cookie' => ['name' => 'dv']]]);

        $response = $this->handle(self::request('/view', cookies: ['dv' => 'tablet', 'device_view' => 'mobile']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('tablet remembered', $response->getContent());
        self::assertNoCookie($response);
    }

    #[Group('CK-07')]
    public function testCustomPath(): void
    {
        $this->boot(['remember' => ['cookie' => ['path' => '/shop']]]);

        self::assertSetCookie('device_view=mobile; path=/shop; httponly; samesite=lax', $this->switch());
    }

    #[Group('CK-08')]
    public function testCustomDomain(): void
    {
        $this->boot(['remember' => ['cookie' => ['domain' => '.example.com']]]);

        self::assertSetCookie('device_view=mobile; path=/; domain=.example.com; httponly; samesite=lax', $this->switch());
    }

    #[Group('CK-09')]
    public function testHttpOnlyFalse(): void
    {
        $this->boot(['remember' => ['cookie' => ['http_only' => false]]]);

        self::assertSetCookie('device_view=mobile; path=/; samesite=lax', $this->switch());
    }

    #[Group('CK-10')]
    public function testSameSiteStrict(): void
    {
        $this->boot(['remember' => ['cookie' => ['same_site' => 'strict']]]);

        self::assertSetCookie('device_view=mobile; path=/; httponly; samesite=strict', $this->switch());
    }

    #[Group('CK-11')]
    public function testSameSiteNoneOverHttps(): void
    {
        $this->boot(['remember' => ['cookie' => ['same_site' => 'none']]]);

        self::assertSetCookie('device_view=mobile; path=/; secure; httponly; samesite=none', $this->switch('https://localhost/view?device_view=mobile'));
    }

    #[Group('CK-12')]
    public function testSameSiteNull(): void
    {
        $this->boot(['remember' => ['cookie' => ['same_site' => null]]]);

        self::assertSetCookie('device_view=mobile; path=/; httponly', $this->switch());
    }

    #[Group('CK-13')]
    public function testRawTrue(): void
    {
        $this->boot(['remember' => ['cookie' => ['raw' => true]]]);

        $response = $this->switch();

        self::assertSetCookie(self::sc('mobile'), $response);
        self::assertTrue($response->headers->getCookies()[0]->isRaw());
    }

    #[Group('CK-14')]
    public function testAPersistentCookieOverHttps(): void
    {
        $this->boot(['consent' => ['provider' => 'tarteaucitron']]);

        $response = $this->handle(self::request('https://localhost/view?device_view=mobile', cookies: ['tarteaucitron' => '!device_view=true']));

        self::assertSetCookie(self::scs('mobile', 31536000), $response);
    }

    public function testRawFalseByDefault(): void
    {
        self::assertFalse($this->switch()->headers->getCookies()[0]->isRaw());
    }

    #[Group('CK-15')]
    public function testTheExpiredCookieCarriesTheConfiguredAttributes(): void
    {
        $this->boot(['remember' => ['cookie' => ['name' => 'dv', 'path' => '/shop', 'domain' => '.example.com']]]);

        $response = $this->handle(self::request('/view', cookies: ['dv' => 'phone']));

        self::assertSetCookie('dv=deleted; expires=E(-31536001); Max-Age=0; path=/shop; domain=.example.com; httponly; samesite=lax', $response);
    }

    public function testTheExpiredCookieIsSecureOverHttps(): void
    {
        $response = $this->handle(self::request('https://localhost/view', cookies: ['device_view' => 'phone']));

        self::assertSetCookie('device_view=deleted; expires=E(-31536001); Max-Age=0; path=/; secure; httponly; samesite=lax', $response);
    }

    #[Group('CK-16')]
    public function testSameSiteNoneForcesSecureEvenOverHttp(): void
    {
        $this->boot(['remember' => ['cookie' => ['same_site' => 'none']]]);

        self::assertSetCookie('device_view=mobile; path=/; secure; httponly; samesite=none', $this->switch('http://localhost/view?device_view=mobile'));
    }

    public function testSameSiteNoneWithSecureTrue(): void
    {
        $this->boot(['remember' => ['cookie' => ['same_site' => 'none', 'secure' => true]]]);

        self::assertSetCookie('device_view=mobile; path=/; secure; httponly; samesite=none', $this->switch());
    }

    private function switch(string $url = '/view?device_view=mobile'): Response
    {
        $response = $this->handle(self::request($url));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/view', $response->headers->get('Location'));

        return $response;
    }
}
